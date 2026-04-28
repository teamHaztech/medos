<?php

namespace App\Modules\AIReceptionist\Services;

use App\Modules\AIReceptionist\Enums\ConversationState;
use App\Modules\AIReceptionist\Enums\Intent;
use App\Modules\AIReceptionist\Events\ConversationStarted;
use App\Modules\AIReceptionist\Events\EmergencyDetected;
use App\Modules\AIReceptionist\Events\EscalationRequested;
use App\Modules\AIReceptionist\Handlers\BookingHandler;
use App\Modules\AIReceptionist\Handlers\IntakeHandler;
use App\Modules\AIReceptionist\Models\Conversation;
use App\Modules\AIReceptionist\Prompts\SystemPrompts;
use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Models\Hospital;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Facades\Log;

class ConversationManager
{
    public function __construct(
        private readonly AIService $aiService,
        private readonly EmergencyDetector $emergencyDetector,
        private readonly IntakeHandler $intakeHandler,
        private readonly BookingHandler $bookingHandler,
    ) {}

    // =================================================================
    // Main Entry Point
    // =================================================================

    /**
     * Handle an incoming patient message from any channel.
     *
     * This is the single entry point for the entire AI Receptionist module.
     *
     * @return array{messages: string[], state: string, conversation_id: string}
     */
    public function handleIncomingMessage(
        string $phone,
        string $message,
        Channel $channel,
        ?string $hospitalId = null,
    ): array {
        $hospitalId = $hospitalId ?? config('medos.current_hospital_id');

        Log::info('[ConversationManager] incoming message', [
            'phone'       => $phone,
            'channel'     => $channel->value,
            'hospital_id' => $hospitalId,
        ]);

        // ----- Step 1: Emergency pre-check (fast, no AI) -----
        $emergencyCheck = $this->emergencyDetector->detect($message);

        // ----- Step 2: Find or create the patient -----
        $patient = $this->findOrCreatePatient($phone, $hospitalId);

        // ----- Step 3: Find active conversation or create new one -----
        $isNew        = false;
        $conversation = $this->findActiveConversation($patient, $channel);

        if ($conversation === null) {
            $conversation = $this->createConversation($patient, $channel, $hospitalId);
            $isNew = true;

            event(new ConversationStarted(
                patientId: $patient->id,
                channel: $channel,
                conversationId: $conversation->id,
                hospitalId: $hospitalId,
            ));
        }

        // ----- Step 4: Add incoming message to conversation -----
        $language = $conversation->language ?? $patient->preferredLanguage();
        $conversation->addMessage('patient', $message, $language);

        // ----- Step 5: Handle emergency if detected -----
        if ($emergencyCheck['is_emergency']) {
            return $this->handleEmergency($conversation, $patient, $phone, $message, $emergencyCheck);
        }

        // ----- Step 6: Route to state handler and get response -----
        try {
            $result = $this->processState($conversation, $message);
        } catch (\Throwable $e) {
            Log::error('[ConversationManager] state processing failed', [
                'conversation_id' => $conversation->id,
                'state'           => $conversation->state,
                'error'           => $e->getMessage(),
            ]);

            $result = [
                'messages' => ['I apologize, I\'m experiencing a technical issue. Please try again in a moment, or type "help" to speak with our staff.'],
            ];
        }

        // ----- Step 7: Add response messages to conversation -----
        foreach ($result['messages'] as $responseMessage) {
            $conversation->addMessage('assistant', $responseMessage, $language);
        }

        // ----- Step 8: Check for state transition -----
        $newState = $this->shouldTransition($conversation, $result);

        if ($newState !== null) {
            $conversation->transition($newState->value);

            Log::info('[ConversationManager] state transition', [
                'conversation_id' => $conversation->id,
                'from'            => $result['current_state'] ?? $conversation->state,
                'to'              => $newState->value,
            ]);
        }

        return [
            'messages'        => $result['messages'],
            'state'           => $conversation->state,
            'conversation_id' => $conversation->id,
        ];
    }

    // =================================================================
    // State Machine Processor
    // =================================================================

    /**
     * Process the current conversation state and produce a response.
     *
     * @return array{messages: string[], current_state: string, intent: ?string, intake_data: ?array, booking_data: ?array}
     */
    public function processState(Conversation $conversation, string $message): array
    {
        $state = ConversationState::tryFrom($conversation->state) ?? ConversationState::Greeting;

        Log::debug('[ConversationManager] processing state', [
            'conversation_id' => $conversation->id,
            'state'           => $state->value,
        ]);

        return match ($state) {
            ConversationState::Greeting,
            ConversationState::LanguageDetection => $this->handleGreeting($conversation, $message),

            ConversationState::Intake            => $this->handleIntake($conversation, $message),

            ConversationState::Triage            => $this->handleTriage($conversation, $message),

            ConversationState::InsuranceCheck    => $this->handleInsuranceCheck($conversation, $message),

            ConversationState::Booking           => $this->handleBooking($conversation, $message),

            ConversationState::Confirmation      => $this->handleConfirmation($conversation, $message),

            ConversationState::Escalated         => $this->handleEscalatedState($conversation, $message),

            ConversationState::Emergency         => $this->handleEmergencyState($conversation, $message),

            ConversationState::Completed         => $this->handleCompleted($conversation, $message),
        };
    }

    /**
     * Determine if the conversation should transition to a new state.
     */
    public function shouldTransition(Conversation $conversation, array $aiResponse): ?ConversationState
    {
        $currentState = ConversationState::tryFrom($conversation->state) ?? ConversationState::Greeting;
        $possibleNext = $currentState->next();

        if (empty($possibleNext)) {
            return null;
        }

        // Greeting -> Intake: once we have detected intent as booking-related
        if ($currentState === ConversationState::Greeting || $currentState === ConversationState::LanguageDetection) {
            $intent = isset($aiResponse['intent']) ? Intent::tryFrom($aiResponse['intent']) : null;

            if ($intent?->requiresEscalation()) {
                return ConversationState::Escalated;
            }

            if ($intent?->isBookingRelated() || $intent === Intent::GeneralInquiry) {
                return ConversationState::Intake;
            }

            // Stay in greeting if we cannot determine intent yet
            return null;
        }

        // Intake -> Triage: once intake data is complete
        if ($currentState === ConversationState::Intake) {
            if (!empty($aiResponse['intake_data']) && ($aiResponse['intake_data']['is_complete'] ?? false)) {
                return ConversationState::Triage;
            }

            return null;
        }

        // Triage -> InsuranceCheck or Booking
        if ($currentState === ConversationState::Triage) {
            $triageComplete = $aiResponse['triage_complete'] ?? false;

            if ($triageComplete) {
                // Check if patient has insurance to verify
                $patient = $conversation->patient;

                if ($patient && $patient->insurance_provider) {
                    return ConversationState::InsuranceCheck;
                }

                return ConversationState::Booking;
            }

            return null;
        }

        // InsuranceCheck -> Booking
        if ($currentState === ConversationState::InsuranceCheck) {
            if ($aiResponse['insurance_checked'] ?? false) {
                return ConversationState::Booking;
            }

            return null;
        }

        // Booking -> Confirmation
        if ($currentState === ConversationState::Booking) {
            if ($aiResponse['is_confirmed'] ?? false) {
                return ConversationState::Confirmation;
            }

            return null;
        }

        // Confirmation -> Completed
        if ($currentState === ConversationState::Confirmation) {
            return ConversationState::Completed;
        }

        return null;
    }

    /**
     * Escalate the conversation to a human staff member.
     *
     * @return array{messages: string[]}
     */
    public function handleEscalation(Conversation $conversation, string $reason): array
    {
        $conversation->transition(ConversationState::Escalated->value);
        $conversation->escalated_at = now();
        $conversation->save();

        $language = $conversation->language ?? 'en';

        event(new EscalationRequested(
            conversationId: $conversation->id,
            patientId: $conversation->patient_id,
            reason: $reason,
            hospitalId: $conversation->hospital_id,
        ));

        Log::info('[ConversationManager] conversation escalated', [
            'conversation_id' => $conversation->id,
            'reason'          => $reason,
        ]);

        $message = match ($language) {
            'hi' => 'मैं आपको हमारी टीम के एक सदस्य से जोड़ रहा हूँ। कृपया कुछ क्षण प्रतीक्षा करें।',
            'ar' => 'سأقوم بتحويلك إلى أحد أعضاء فريقنا. يرجى الانتظار لحظة.',
            default => "I'm connecting you with a member of our team. Please hold on for a moment. Reason: {$reason}",
        };

        return ['messages' => [$message]];
    }

    // =================================================================
    // State Handlers
    // =================================================================

    private function handleGreeting(Conversation $conversation, string $message): array
    {
        $hospital = Hospital::find($conversation->hospital_id);
        $patient  = Patient::find($conversation->patient_id);

        // Detect language from the first message
        $langResult = $this->aiService->detectLanguage($message);

        if ($langResult['confidence'] >= config('medos.languages.detection_confidence_threshold', 0.8)) {
            $conversation->language = $langResult['language'];
            $conversation->save();

            // Update patient preferred language if not set
            if ($patient && empty($patient->preferred_language)) {
                $patient->preferred_language = $langResult['language'];
                $patient->save();
            }
        }

        // Detect intent
        $intent = $this->aiService->extractIntent($message);

        // Generate greeting response
        $systemPrompt = SystemPrompts::greeting($hospital, $patient);
        $aiMessages   = [['role' => 'user', 'content' => $message]];

        try {
            $aiResponse = $this->aiService->chat($systemPrompt, $aiMessages);
            $responseText = $aiResponse['content'];
        } catch (\Throwable $e) {
            Log::error('[ConversationManager] greeting AI failed', ['error' => $e->getMessage()]);
            $responseText = $this->fallbackGreeting($hospital, $patient);
        }

        // Store reasoning trace
        $trace = $conversation->ai_reasoning_trace ?? [];
        $trace['language_detection'] = $langResult;
        $trace['detected_intent']    = $intent->value;
        $conversation->ai_reasoning_trace = $trace;
        $conversation->save();

        return [
            'messages'      => [$responseText],
            'current_state' => ConversationState::Greeting->value,
            'intent'        => $intent->value,
        ];
    }

    private function handleIntake(Conversation $conversation, string $message): array
    {
        $result = $this->intakeHandler->handle($conversation, $message);

        // Store intake data in the reasoning trace
        if ($result['intake_data'] !== null) {
            $trace = $conversation->ai_reasoning_trace ?? [];
            $trace['intake_data'] = $result['intake_data'];
            $conversation->ai_reasoning_trace = $trace;
            $conversation->save();
        }

        return [
            'messages'      => [$result['response']],
            'current_state' => ConversationState::Intake->value,
            'intake_data'   => $result['intake_data'],
        ];
    }

    private function handleTriage(Conversation $conversation, string $message): array
    {
        $trace      = $conversation->ai_reasoning_trace ?? [];
        $intakeData = $trace['intake_data'] ?? [];

        try {
            // Delegate to TriageService if available
            $triageService = app()->make('App\\Modules\\Triage\\Services\\TriageService');

            $triageResult = $triageService->assess([
                'hospital_id'     => $conversation->hospital_id,
                'patient_id'      => $conversation->patient_id,
                'chief_complaint' => $intakeData['chief_complaint'] ?? '',
                'symptoms'        => $intakeData['symptoms'] ?? [],
                'severity'        => $intakeData['severity'] ?? null,
                'duration'        => $intakeData['duration'] ?? null,
            ]);

            $trace['triage_result'] = $triageResult;
            $conversation->ai_reasoning_trace = $trace;
            $conversation->save();

            $language = $conversation->language ?? 'en';
            $urgency  = $triageResult['urgency_level'] ?? 'standard';

            $responseText = match ($language) {
                'hi' => "आपकी जानकारी के आधार पर, मैंने आपकी प्राथमिकता {$urgency} के रूप में निर्धारित की है। अब मैं आपके लिए एक उपयुक्त अपॉइंटमेंट ढूंढता हूँ।",
                'ar' => "بناءً على المعلومات المقدمة، تم تصنيف حالتك كـ {$urgency}. سأبحث لك عن موعد مناسب الآن.",
                default => "Based on the information you've shared, I've assessed your priority as {$urgency}. Let me find a suitable appointment for you now.",
            };

            return [
                'messages'       => [$responseText],
                'current_state'  => ConversationState::Triage->value,
                'triage_complete' => true,
            ];
        } catch (\Throwable $e) {
            Log::info('[ConversationManager] triage service not available, proceeding to booking', [
                'error' => $e->getMessage(),
            ]);

            // If triage service is not bound yet, skip triage and proceed
            return [
                'messages'       => ['Thank you for providing that information. Let me find available appointment slots for you.'],
                'current_state'  => ConversationState::Triage->value,
                'triage_complete' => true,
            ];
        }
    }

    private function handleInsuranceCheck(Conversation $conversation, string $message): array
    {
        $patient = $conversation->patient;
        $language = $conversation->language ?? 'en';

        try {
            // Delegate to InsuranceService if available
            $insuranceService = app()->make('App\\Modules\\Insurance\\Services\\InsuranceService');

            $verificationResult = $insuranceService->verifyEligibility([
                'patient_id'    => $conversation->patient_id,
                'provider'      => $patient?->insurance_provider,
                'policy_number' => $patient?->insurance_policy_number,
            ]);

            $trace = $conversation->ai_reasoning_trace ?? [];
            $trace['insurance_verification'] = $verificationResult;
            $conversation->ai_reasoning_trace = $trace;
            $conversation->save();

            $isEligible = $verificationResult['eligible'] ?? false;

            $responseText = $isEligible
                ? match ($language) {
                    'hi' => 'आपका बीमा सत्यापित हो गया है। अब मैं आपके लिए अपॉइंटमेंट ढूंढता हूँ।',
                    'ar' => 'تم التحقق من التأمين الخاص بك. سأبحث لك عن موعد الآن.',
                    default => 'Your insurance has been verified successfully. Let me find available appointments for you.',
                }
                : match ($language) {
                    'hi' => 'मैं आपके बीमा को सत्यापित नहीं कर सका। हम बिना बीमा के भी आपकी सेवा कर सकते हैं। क्या आप जारी रखना चाहेंगे?',
                    'ar' => 'لم أتمكن من التحقق من التأمين. يمكننا المتابعة بدون تأمين. هل ترغب في المتابعة؟',
                    default => 'I was unable to verify your insurance at this time. We can still proceed without insurance verification. Would you like to continue?',
                };

            return [
                'messages'          => [$responseText],
                'current_state'     => ConversationState::InsuranceCheck->value,
                'insurance_checked' => true,
            ];
        } catch (\Throwable $e) {
            Log::info('[ConversationManager] insurance service not available, skipping', [
                'error' => $e->getMessage(),
            ]);

            return [
                'messages'          => ['Let me proceed to find available appointment slots for you.'],
                'current_state'     => ConversationState::InsuranceCheck->value,
                'insurance_checked' => true,
            ];
        }
    }

    private function handleBooking(Conversation $conversation, string $message): array
    {
        $trace      = $conversation->ai_reasoning_trace ?? [];
        $intakeData = $trace['intake_data'] ?? [];

        $result = $this->bookingHandler->handle($conversation, $message, $intakeData);

        return [
            'messages'      => [$result['response']],
            'current_state' => ConversationState::Booking->value,
            'is_confirmed'  => $result['is_confirmed'],
            'booking_data'  => $result['slot_selected'] ?? null,
        ];
    }

    private function handleConfirmation(Conversation $conversation, string $message): array
    {
        $language = $conversation->language ?? 'en';
        $trace    = $conversation->ai_reasoning_trace ?? [];
        $booking  = $trace['pending_booking'] ?? [];

        $responseText = match ($language) {
            'hi' => "आपकी अपॉइंटमेंट की पुष्टि हो गई है। कृपया समय पर पहुँचें। यदि आपको रद्द या पुनर्निर्धारित करने की आवश्यकता है, तो कृपया हमसे संपर्क करें। ध्यान रखें!",
            'ar' => "تم تأكيد موعدك. يرجى الحضور في الوقت المحدد. إذا كنت بحاجة إلى الإلغاء أو إعادة الجدولة، يرجى التواصل معنا. نتمنى لك السلامة!",
            default => "Your appointment has been confirmed! Please arrive 15 minutes early for registration. If you need to cancel or reschedule, just message us. Take care!",
        };

        // Mark the conversation as completed
        $conversation->ended_at = now();
        $conversation->save();

        return [
            'messages'      => [$responseText],
            'current_state' => ConversationState::Confirmation->value,
        ];
    }

    private function handleEscalatedState(Conversation $conversation, string $message): array
    {
        $language = $conversation->language ?? 'en';

        $responseText = match ($language) {
            'hi' => 'आपकी बातचीत हमारे स्टाफ को भेज दी गई है। वे जल्द ही आपसे संपर्क करेंगे। कृपया प्रतीक्षा करें।',
            'ar' => 'تم تحويل محادثتك إلى فريقنا. سيتواصلون معك قريبًا. يرجى الانتظار.',
            default => 'Your conversation has been passed to our staff. They will reach out to you shortly. Please hold on.',
        };

        return [
            'messages'      => [$responseText],
            'current_state' => ConversationState::Escalated->value,
        ];
    }

    private function handleEmergencyState(Conversation $conversation, string $message): array
    {
        $language = $conversation->language ?? 'en';

        $responseText = match ($language) {
            'hi' => "यह एक आपातकालीन स्थिति लगती है। कृपया तुरंत 112 पर कॉल करें या नजदीकी आपातकालीन विभाग में जाएं। हमने आपकी स्थिति के बारे में हमारी आपातकालीन टीम को सूचित कर दिया है।",
            'ar' => "يبدو أن هذه حالة طوارئ. يرجى الاتصال بـ 112 فورًا أو التوجه إلى أقرب قسم طوارئ. لقد أبلغنا فريق الطوارئ لدينا بحالتك.",
            default => "This appears to be an emergency situation. Please call 112 immediately or go to the nearest emergency department. We have notified our emergency team about your situation.",
        };

        return [
            'messages'      => [$responseText],
            'current_state' => ConversationState::Emergency->value,
        ];
    }

    private function handleCompleted(Conversation $conversation, string $message): array
    {
        // If a patient sends a message to a completed conversation, start a new flow
        $language = $conversation->language ?? 'en';

        $responseText = match ($language) {
            'hi' => 'नमस्ते! आप फिर से हमसे संपर्क कर रहे हैं। मैं आपकी कैसे मदद कर सकता हूँ?',
            'ar' => 'مرحبًا! أنت تتواصل معنا مرة أخرى. كيف يمكنني مساعدتك؟',
            default => 'Hello! You\'re reaching out again. How can I help you today?',
        };

        // End this conversation and the caller will create a new one next time
        $conversation->ended_at = now();
        $conversation->save();

        return [
            'messages'      => [$responseText],
            'current_state' => ConversationState::Completed->value,
        ];
    }

    // =================================================================
    // Emergency Handling
    // =================================================================

    private function handleEmergency(
        Conversation $conversation,
        Patient $patient,
        string $phone,
        string $message,
        array $emergencyCheck,
    ): array {
        $conversation->transition(ConversationState::Emergency->value);

        event(new EmergencyDetected(
            patientId: $patient->id,
            conversationId: $conversation->id,
            phone: $phone,
            message: $message,
            detectionResult: $emergencyCheck,
            hospitalId: $conversation->hospital_id,
        ));

        Log::critical('[ConversationManager] EMERGENCY detected', [
            'conversation_id' => $conversation->id,
            'patient_id'      => $patient->id,
            'phone'           => $phone,
            'keywords'        => $emergencyCheck['matched_keywords'],
        ]);

        $language = $conversation->language ?? $patient->preferredLanguage();

        $responseText = match ($language) {
            'hi' => "⚠️ आपातकाल का पता चला है। कृपया तुरंत 112 पर कॉल करें या नजदीकी आपातकालीन विभाग में जाएं। हमने आपके अस्पताल की आपातकालीन टीम को तुरंत सूचित कर दिया है। कृपया शांत रहें — मदद आ रही है।",
            'ar' => "⚠️ تم الكشف عن حالة طوارئ. يرجى الاتصال بـ 112 فورًا أو التوجه إلى أقرب قسم طوارئ. لقد أبلغنا فريق الطوارئ في المستشفى فورًا. يرجى التزام الهدوء — المساعدة في الطريق.",
            default => "⚠️ Emergency detected. Please call 112 immediately or go to the nearest emergency department. We have immediately notified our hospital's emergency team. Please stay calm — help is on the way.",
        };

        $conversation->addMessage('assistant', $responseText, $language, [
            'emergency_detection' => $emergencyCheck,
        ]);

        return [
            'messages'        => [$responseText],
            'state'           => ConversationState::Emergency->value,
            'conversation_id' => $conversation->id,
        ];
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Find or create a patient by phone number within a hospital.
     */
    private function findOrCreatePatient(string $phone, ?string $hospitalId): Patient
    {
        $query = Patient::where('phone', $phone);

        if ($hospitalId) {
            $query->where('hospital_id', $hospitalId);
        }

        $patient = $query->first();

        if ($patient === null) {
            $patient = Patient::create([
                'hospital_id' => $hospitalId,
                'phone'       => $phone,
                'first_name'  => 'Unknown',
                'last_name'   => '',
                'metadata'    => ['source' => 'ai_receptionist', 'created_at_step' => 'conversation_start'],
            ]);

            Log::info('[ConversationManager] new patient created from incoming message', [
                'patient_id'  => $patient->id,
                'phone'       => $phone,
                'hospital_id' => $hospitalId,
            ]);
        }

        return $patient;
    }

    /**
     * Find an active (non-ended) conversation for a patient on a channel.
     */
    private function findActiveConversation(Patient $patient, Channel $channel): ?Conversation
    {
        return Conversation::where('patient_id', $patient->id)
            ->where('channel', $channel->value)
            ->whereNull('ended_at')
            ->latest()
            ->first();
    }

    /**
     * Create a new conversation.
     */
    private function createConversation(Patient $patient, Channel $channel, ?string $hospitalId): Conversation
    {
        return Conversation::create([
            'hospital_id' => $hospitalId,
            'patient_id'  => $patient->id,
            'channel'     => $channel->value,
            'state'       => ConversationState::Greeting->value,
            'language'    => $patient->preferredLanguage(),
            'messages'    => [],
            'ai_reasoning_trace' => [],
            'started_at'  => now(),
        ]);
    }

    /**
     * Generate a fallback greeting when AI is unavailable.
     */
    private function fallbackGreeting(Hospital $hospital, ?Patient $patient): string
    {
        $name = $patient?->full_name ?? '';
        $greeting = $name ? "Hello {$name}!" : 'Hello!';

        return "{$greeting} Welcome to {$hospital->name}. How can I help you today?";
    }
}

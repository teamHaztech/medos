<?php

namespace App\Modules\AIReceptionist\Handlers;

use App\Modules\AIReceptionist\Models\Conversation;
use App\Modules\AIReceptionist\Prompts\SystemPrompts;
use App\Modules\AIReceptionist\Services\AIService;
use Illuminate\Support\Facades\Log;

class BookingHandler
{
    public function __construct(
        private readonly AIService $aiService,
    ) {}

    /**
     * Handle a message during the Booking conversation state.
     *
     * @param  array  $intakeData  Structured intake data collected during the Intake state.
     * @return array{response: string, slot_selected: ?array, is_confirmed: bool}
     */
    public function handle(Conversation $conversation, string $message, array $intakeData): array
    {
        try {
            // Fetch available slots from the scheduling service (if bound)
            $slots = $this->fetchAvailableSlots($conversation, $intakeData);

            // Fetch insurance info from the patient
            $insuranceInfo = $this->fetchInsuranceInfo($conversation);

            $systemPrompt = SystemPrompts::booking($slots, $insuranceInfo);

            $aiMessages = $this->buildAIMessages($conversation, $message);

            $aiResponse   = $this->aiService->chat($systemPrompt, $aiMessages);
            $responseText = $aiResponse['content'];

            // Check if the patient confirmed a slot selection
            $selection   = $this->parseSlotSelection($responseText);
            $isConfirmed = $selection !== null && ($selection['confirmed'] ?? false);

            $cleanResponse = $this->stripJsonBlock($responseText);

            // If confirmed, create the appointment
            if ($isConfirmed && isset($selection['selected_slot_index'])) {
                $slotIndex = $selection['selected_slot_index'];

                if (isset($slots[$slotIndex])) {
                    $bookingResult = $this->confirmBooking($conversation, $slots[$slotIndex]);

                    return [
                        'response'      => $cleanResponse,
                        'slot_selected' => $slots[$slotIndex],
                        'is_confirmed'  => true,
                        'booking'       => $bookingResult,
                    ];
                }
            }

            return [
                'response'      => $cleanResponse,
                'slot_selected' => null,
                'is_confirmed'  => false,
            ];
        } catch (\Throwable $e) {
            Log::error('[BookingHandler] failed', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);

            return [
                'response'      => 'I\'m sorry, I\'m having trouble accessing the scheduling system right now. Let me connect you with our staff who can help you book an appointment.',
                'slot_selected' => null,
                'is_confirmed'  => false,
            ];
        }
    }

    /**
     * Format available slots into a patient-friendly string.
     */
    public function presentSlotOptions(array $slots, string $language): string
    {
        if (empty($slots)) {
            return $language === 'hi'
                ? 'क्षमा करें, अभी कोई उपलब्ध स्लॉट नहीं है।'
                : 'I\'m sorry, there are no available slots at this time.';
        }

        $lines = [];

        foreach ($slots as $index => $slot) {
            $num        = $index + 1;
            $date       = $slot['date'] ?? 'TBD';
            $time       = $slot['time'] ?? 'TBD';
            $doctor     = $slot['doctor_name'] ?? 'Available Doctor';
            $specialty  = $slot['specialty'] ?? '';

            $specialtyText = $specialty ? " ({$specialty})" : '';
            $lines[] = "{$num}. {$date} at {$time} — Dr. {$doctor}{$specialtyText}";
        }

        return implode("\n", $lines);
    }

    /**
     * Confirm the booking by creating an appointment via the scheduling service.
     *
     * @return array{success: bool, appointment_id: ?string, message: string}
     */
    public function confirmBooking(Conversation $conversation, array $selectedSlot): array
    {
        try {
            // Attempt to use the scheduling service if it is bound in the container
            $schedulingService = app()->make('App\\Modules\\Appointment\\Services\\SchedulingService');

            $appointment = $schedulingService->bookSlot([
                'hospital_id' => $conversation->hospital_id,
                'patient_id'  => $conversation->patient_id,
                'doctor_id'   => $selectedSlot['doctor_id'] ?? null,
                'date'        => $selectedSlot['date'] ?? null,
                'time'        => $selectedSlot['time'] ?? null,
                'source'      => 'ai_receptionist',
                'conversation_id' => $conversation->id,
            ]);

            return [
                'success'        => true,
                'appointment_id' => $appointment->id ?? null,
                'message'        => 'Appointment booked successfully.',
            ];
        } catch (\Throwable $e) {
            Log::warning('[BookingHandler] scheduling service unavailable, storing booking intent', [
                'conversation_id' => $conversation->id,
                'slot'            => $selectedSlot,
                'error'           => $e->getMessage(),
            ]);

            // Store the booking intent in the conversation trace so staff can follow up
            $trace = $conversation->ai_reasoning_trace ?? [];
            $trace['pending_booking'] = [
                'slot'         => $selectedSlot,
                'requested_at' => now()->toIso8601String(),
            ];
            $conversation->ai_reasoning_trace = $trace;
            $conversation->save();

            return [
                'success'        => false,
                'appointment_id' => null,
                'message'        => 'Booking recorded. Our staff will confirm your appointment shortly.',
            ];
        }
    }

    // -----------------------------------------------------------------
    // Private Helpers
    // -----------------------------------------------------------------

    /**
     * Fetch available slots from the scheduling service.
     */
    private function fetchAvailableSlots(Conversation $conversation, array $intakeData): array
    {
        try {
            $schedulingService = app()->make('App\\Modules\\Appointment\\Services\\SchedulingService');

            return $schedulingService->getAvailableSlots([
                'hospital_id'     => $conversation->hospital_id,
                'chief_complaint' => $intakeData['chief_complaint'] ?? null,
                'specialty'       => $intakeData['suggested_specialty'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::info('[BookingHandler] scheduling service not available, returning empty slots', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Fetch insurance information for the patient.
     */
    private function fetchInsuranceInfo(Conversation $conversation): array
    {
        try {
            $patient = $conversation->patient;

            if ($patient) {
                $insurance = $patient->getActiveInsurance();

                if ($insurance) {
                    return [
                        'has_insurance' => true,
                        'provider'      => $patient->insurance_provider,
                        'policy_number' => $patient->insurance_policy_number,
                        'details'       => $insurance,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[BookingHandler] could not fetch insurance info', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['has_insurance' => false];
    }

    /**
     * Build AI messages from the conversation history.
     */
    private function buildAIMessages(Conversation $conversation, string $currentMessage): array
    {
        $aiMessages = [];

        foreach ($conversation->messages ?? [] as $msg) {
            $role = match ($msg['role'] ?? '') {
                'patient'   => 'user',
                'assistant' => 'assistant',
                default     => null,
            };

            if ($role !== null) {
                $aiMessages[] = [
                    'role'    => $role,
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        $aiMessages[] = [
            'role'    => 'user',
            'content' => $currentMessage,
        ];

        return $aiMessages;
    }

    /**
     * Parse slot selection JSON from the AI response.
     */
    private function parseSlotSelection(string $text): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);

            if (is_array($decoded) && isset($decoded['selected_slot_index'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Strip JSON code blocks from the response.
     */
    private function stripJsonBlock(string $text): string
    {
        return trim(preg_replace('/```(?:json)?\s*\{[\s\S]*?\}\s*```/', '', $text));
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VoiceCall;
use App\Models\VoiceCallSetting;
use App\Models\VoiceCallTranscript;
use App\Models\VoiceCallCallback;
use App\Modules\Patient\Models\Patient;
use App\Modules\Core\Models\Staff;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VoiceCallController extends Controller
{
    /**
     * Get the current hospital_id from auth.
     */
    private function hospitalId(): string
    {
        return auth()->user()->hospital_id;
    }

    /**
     * Check if voice call tables exist.
     */
    private function tablesExist(): bool
    {
        return Schema::hasTable('voice_calls')
            && Schema::hasTable('voice_call_settings')
            && Schema::hasTable('voice_call_transcripts')
            && Schema::hasTable('voice_call_callbacks');
    }

    /**
     * Main voice call dashboard.
     */
    public function dashboard(Request $request)
    {
        if (!$this->tablesExist()) {
            return view('voice-calls.dashboard', [
                'settings' => null,
                'todayStats' => [],
                'activeCalls' => collect(),
                'recentCalls' => collect(),
                'callsByHour' => array_fill(0, 24, 0),
                'topReasons' => collect(),
                'callbackQueue' => 0,
            ]);
        }

        $hid = $this->hospitalId();

        $settings = VoiceCallSetting::firstOrCreate(
            ['hospital_id' => $hid],
            [
                'id' => Str::uuid()->toString(),
                'is_enabled' => false,
                'provider' => null,
                'phone_numbers' => [],
                'greeting_message' => 'Welcome to our hospital. How can I help you today?',
                'default_language' => 'en',
                'supported_languages' => ['en', 'hi'],
                'voice_name' => null,
                'max_concurrent_calls' => 5,
                'auto_callback_enabled' => true,
                'auto_callback_delay_minutes' => 5,
                'emergency_transfer_number' => null,
                'business_hours' => null,
                'after_hours_message' => 'We are currently closed. Please call back during business hours or press 1 for emergencies.',
                'ai_model' => 'claude-haiku-4-5-20251001',
                'ai_temperature' => 0.3,
            ]
        );

        $today = Carbon::today();

        $todayCalls = VoiceCall::where('hospital_id', $hid)
            ->whereDate('created_at', $today);

        $totalCalls = (clone $todayCalls)->count();
        $answered = (clone $todayCalls)->where('status', 'completed')->count();
        $missed = (clone $todayCalls)->where('status', 'missed')->count();
        $avgDuration = (clone $todayCalls)->whereNotNull('duration_seconds')->avg('duration_seconds') ?? 0;
        $aiResolved = (clone $todayCalls)->where('ai_handled', true)
            ->where('status', 'completed')
            ->whereNull('transferred_to')
            ->count();
        $humanTransferred = (clone $todayCalls)->whereNotNull('transferred_to')->count();
        $totalCost = (clone $todayCalls)->sum('cost_amount') ?? 0;

        $todayStats = [
            'total_calls' => $totalCalls,
            'answered' => $answered,
            'missed' => $missed,
            'avg_duration' => round($avgDuration),
            'ai_resolved' => $aiResolved,
            'human_transferred' => $humanTransferred,
            'total_cost' => round($totalCost, 2),
        ];

        $activeCalls = VoiceCall::where('hospital_id', $hid)
            ->where('status', 'in_progress')
            ->with(['patient', 'transcripts'])
            ->orderBy('started_at', 'desc')
            ->get();

        $recentCalls = VoiceCall::where('hospital_id', $hid)
            ->whereDate('created_at', $today)
            ->with('patient')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Calls by hour — build array of 24 zeroed slots, then fill from DB
        $callsByHour = array_fill(0, 24, 0);
        $hourCounts = VoiceCall::where('hospital_id', $hid)
            ->whereDate('created_at', $today)
            ->selectRaw("strftime('%H', created_at) as hour, count(*) as cnt")
            ->groupByRaw("strftime('%H', created_at)")
            ->pluck('cnt', 'hour');
        foreach ($hourCounts as $hour => $count) {
            $callsByHour[(int) $hour] = $count;
        }

        $topReasons = VoiceCall::where('hospital_id', $hid)
            ->whereDate('created_at', $today)
            ->whereNotNull('call_purpose')
            ->selectRaw('call_purpose, count(*) as cnt')
            ->groupBy('call_purpose')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $callbackQueue = VoiceCallCallback::where('hospital_id', $hid)
            ->where('status', 'pending')
            ->count();

        return view('voice-calls.dashboard', compact(
            'settings',
            'todayStats',
            'activeCalls',
            'recentCalls',
            'callsByHour',
            'topReasons',
            'callbackQueue',
        ));
    }

    /**
     * Full call history with filters.
     */
    public function callLog(Request $request)
    {
        if (!$this->tablesExist()) {
            return view('voice-calls.call-log', [
                'calls' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
                'stats' => [],
            ]);
        }

        $hid = $this->hospitalId();

        $query = VoiceCall::where('hospital_id', $hid)->with('patient');

        // Date range filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Direction filter
        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        // Call purpose filter
        if ($request->filled('call_purpose')) {
            $query->where('call_purpose', $request->input('call_purpose'));
        }

        // Language filter
        if ($request->filled('language_used')) {
            $query->where('language_used', $request->input('language_used'));
        }

        // Stats for the filtered period
        $statsQuery = clone $query;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'answered' => (clone $statsQuery)->where('status', 'completed')->count(),
            'missed' => (clone $statsQuery)->where('status', 'missed')->count(),
            'avg_duration' => round((clone $statsQuery)->whereNotNull('duration_seconds')->avg('duration_seconds') ?? 0),
            'ai_resolved' => (clone $statsQuery)->where('ai_handled', true)->whereNull('transferred_to')->where('status', 'completed')->count(),
            'total_cost' => round((clone $statsQuery)->sum('cost_amount') ?? 0, 2),
        ];

        $calls = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        return view('voice-calls.call-log', compact('calls', 'stats'));
    }

    /**
     * Single call detail view.
     */
    public function callDetail(string $id)
    {
        if (!$this->tablesExist()) {
            abort(404);
        }

        $call = VoiceCall::where('hospital_id', $this->hospitalId())
            ->where('id', $id)
            ->with(['patient', 'transcripts' => function ($q) {
                $q->orderBy('timestamp_ms', 'asc');
            }])
            ->first();

        if (!$call) {
            abort(404);
        }

        return view('voice-calls.call-detail', compact('call'));
    }

    /**
     * GET: Show settings page.
     * POST: Save settings.
     */
    public function settings(Request $request)
    {
        if (!$this->tablesExist()) {
            return view('voice-calls.settings', ['settings' => null]);
        }

        $hid = $this->hospitalId();

        $settings = VoiceCallSetting::firstOrCreate(
            ['hospital_id' => $hid],
            [
                'id' => Str::uuid()->toString(),
                'is_enabled' => false,
                'default_language' => 'en',
                'supported_languages' => ['en', 'hi'],
                'max_concurrent_calls' => 5,
                'auto_callback_enabled' => true,
                'auto_callback_delay_minutes' => 5,
                'ai_model' => 'claude-haiku-4-5-20251001',
                'ai_temperature' => 0.3,
            ]
        );

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'provider' => 'nullable|string|max:50',
                'phone_numbers' => 'nullable|string|max:500',
                'greeting_message' => 'nullable|string|max:1000',
                'default_language' => 'required|string|max:10',
                'supported_languages' => 'nullable|array',
                'supported_languages.*' => 'string|max:10',
                'voice_name' => 'nullable|string|max:100',
                'max_concurrent_calls' => 'required|integer|min:1|max:50',
                'auto_callback_enabled' => 'nullable|boolean',
                'auto_callback_delay_minutes' => 'required|integer|min:1|max:60',
                'emergency_transfer_number' => 'nullable|string|max:20',
                'business_hours' => 'nullable|string|max:2000',
                'after_hours_message' => 'nullable|string|max:1000',
                'ai_model' => 'nullable|string|max:100',
                'ai_temperature' => 'nullable|numeric|min:0|max:1',
            ]);

            // Convert comma-separated phone numbers to array
            if (isset($validated['phone_numbers']) && is_string($validated['phone_numbers'])) {
                $validated['phone_numbers'] = array_map('trim', explode(',', $validated['phone_numbers']));
                $validated['phone_numbers'] = array_filter($validated['phone_numbers']);
                $validated['phone_numbers'] = array_values($validated['phone_numbers']);
            }

            // Parse business_hours JSON string if provided
            if (isset($validated['business_hours']) && is_string($validated['business_hours'])) {
                $decoded = json_decode($validated['business_hours'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $validated['business_hours'] = $decoded;
                }
            }

            $validated['auto_callback_enabled'] = $request->boolean('auto_callback_enabled');

            $settings->update($validated);

            return redirect()->back()->with('success', 'Voice call settings saved successfully.');
        }

        return view('voice-calls.settings', compact('settings'));
    }

    /**
     * Toggle voice calling enabled/disabled.
     */
    public function toggleEnabled(Request $request)
    {
        if (!$this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Tables not migrated'], 500);
        }

        $hid = $this->hospitalId();

        $settings = VoiceCallSetting::where('hospital_id', $hid)->first();

        if (!$settings) {
            return response()->json(['success' => false, 'error' => 'Settings not found'], 404);
        }

        $settings->is_enabled = !$settings->is_enabled;
        $settings->save();

        return response()->json([
            'success' => true,
            'enabled' => $settings->is_enabled,
        ]);
    }

    /**
     * Live calls JSON for AJAX polling.
     */
    public function liveCallsJson(Request $request)
    {
        if (!$this->tablesExist()) {
            return response()->json([]);
        }

        $hid = $this->hospitalId();

        $calls = VoiceCall::where('hospital_id', $hid)
            ->where('status', 'in_progress')
            ->with(['patient', 'transcripts' => function ($q) {
                $q->orderBy('timestamp_ms', 'desc')->limit(1);
            }])
            ->orderBy('started_at', 'desc')
            ->get();

        $data = $calls->map(function ($call) {
            $durationSeconds = $call->started_at
                ? Carbon::parse($call->started_at)->diffInSeconds(now())
                : 0;

            $lastTranscript = $call->transcripts->first();

            return [
                'id' => $call->id,
                'caller_number' => $call->caller_number,
                'patient_name' => $call->patient?->name ?? 'Unknown',
                'duration' => $durationSeconds,
                'status' => $call->status,
                'language_used' => $call->language_used,
                'call_purpose' => $call->call_purpose,
                'sentiment' => $call->sentiment,
                'ai_confidence' => $call->ai_confidence,
                'last_transcript' => $lastTranscript?->content ?? '',
            ];
        });

        return response()->json($data);
    }

    /**
     * Transcript lines for a specific call (for live streaming).
     */
    public function callTranscriptJson(string $id)
    {
        if (!$this->tablesExist()) {
            return response()->json([]);
        }

        $call = VoiceCall::where('hospital_id', $this->hospitalId())
            ->where('id', $id)
            ->first();

        if (!$call) {
            return response()->json([], 404);
        }

        $transcripts = VoiceCallTranscript::where('voice_call_id', $call->id)
            ->orderBy('timestamp_ms', 'asc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'speaker' => $t->speaker,
                    'content' => $t->content,
                    'language' => $t->language,
                    'confidence' => $t->confidence,
                    'timestamp_ms' => $t->timestamp_ms,
                    'created_at' => $t->created_at?->toIso8601String(),
                ];
            });

        return response()->json($transcripts);
    }

    /**
     * Webhook handler for telephony provider (Exotel/Twilio).
     */
    public function handleWebhook(Request $request)
    {
        if (!$this->tablesExist()) {
            return response()->json(['status' => 'ok']);
        }

        $event = $request->input('event') ?? $request->input('EventType') ?? $request->input('type');
        $callSid = $request->input('CallSid') ?? $request->input('call_sid') ?? $request->input('sid');
        $callerNumber = $request->input('From') ?? $request->input('caller_number') ?? $request->input('CallFrom');
        $calledNumber = $request->input('To') ?? $request->input('called_number') ?? $request->input('CallTo');
        $direction = $request->input('Direction') ?? $request->input('direction') ?? 'inbound';

        // Determine hospital from the called number
        $hospitalId = null;
        if ($calledNumber) {
            $settings = VoiceCallSetting::where('is_enabled', true)->get();
            foreach ($settings as $setting) {
                $phones = is_array($setting->phone_numbers) ? $setting->phone_numbers : [];
                if (in_array($calledNumber, $phones)) {
                    $hospitalId = $setting->hospital_id;
                    break;
                }
            }
        }

        if (!$hospitalId) {
            // Fallback: try to find by call_sid if call already exists
            $existingCall = VoiceCall::where('call_sid', $callSid)->first();
            if ($existingCall) {
                $hospitalId = $existingCall->hospital_id;
            }
        }

        if (!$hospitalId) {
            return response()->json(['status' => 'ok', 'message' => 'No matching hospital']);
        }

        try {
            switch ($event) {
                case 'call.ringing':
                case 'ringing':
                case 'initiated':
                    $call = VoiceCall::create([
                        'id' => Str::uuid()->toString(),
                        'hospital_id' => $hospitalId,
                        'call_sid' => $callSid,
                        'direction' => strtolower($direction) === 'outbound' ? 'outbound' : 'inbound',
                        'caller_number' => $callerNumber ?? '',
                        'called_number' => $calledNumber ?? '',
                        'status' => 'ringing',
                        'started_at' => now(),
                    ]);

                    // Try to match patient by phone number
                    if ($callerNumber) {
                        $cleanNumber = preg_replace('/[^0-9]/', '', $callerNumber);
                        $patient = Patient::where('hospital_id', $hospitalId)
                            ->where(function ($q) use ($callerNumber, $cleanNumber) {
                                $q->where('phone', $callerNumber)
                                    ->orWhere('phone', $cleanNumber)
                                    ->orWhere('phone', 'LIKE', '%' . substr($cleanNumber, -10));
                            })
                            ->first();

                        if ($patient) {
                            $call->patient_id = $patient->id;
                            $call->save();
                        }
                    }
                    break;

                case 'call.answered':
                case 'answered':
                case 'in-progress':
                    $call = VoiceCall::where('call_sid', $callSid)->first();
                    if ($call) {
                        $call->status = 'in_progress';
                        $call->answered_at = now();
                        $call->save();
                    }
                    break;

                case 'call.completed':
                case 'completed':
                    $call = VoiceCall::where('call_sid', $callSid)->first();
                    if ($call) {
                        $call->status = 'completed';
                        $call->ended_at = now();
                        if ($call->answered_at) {
                            $call->duration_seconds = Carbon::parse($call->answered_at)->diffInSeconds(now());
                        }
                        if ($call->started_at && $call->answered_at) {
                            $call->ring_duration_seconds = Carbon::parse($call->started_at)->diffInSeconds($call->answered_at);
                        }
                        // Cost from provider
                        if ($request->has('CallCost') || $request->has('cost')) {
                            $call->cost_amount = $request->input('CallCost') ?? $request->input('cost');
                        }
                        $call->save();
                    }
                    break;

                case 'call.missed':
                case 'no-answer':
                case 'busy':
                case 'failed':
                    $call = VoiceCall::where('call_sid', $callSid)->first();
                    if ($call) {
                        $call->status = 'missed';
                        $call->ended_at = now();
                        if ($call->started_at) {
                            $call->ring_duration_seconds = Carbon::parse($call->started_at)->diffInSeconds(now());
                        }
                        $call->save();

                        // Auto-callback if enabled
                        $callSettings = VoiceCallSetting::where('hospital_id', $hospitalId)->first();
                        if ($callSettings && $callSettings->auto_callback_enabled && $callerNumber) {
                            VoiceCallCallback::create([
                                'id' => Str::uuid()->toString(),
                                'hospital_id' => $hospitalId,
                                'caller_number' => $callerNumber,
                                'original_call_id' => $call->id,
                                'status' => 'pending',
                                'attempts' => 0,
                                'max_attempts' => 3,
                                'next_attempt_at' => now()->addMinutes($callSettings->auto_callback_delay_minutes),
                            ]);
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process AI response for voice agent.
     * Keyword-based intent detection — actual AI generation is handled by the voice agent server.
     */
    public function processAiResponse(Request $request)
    {
        if (!$this->tablesExist()) {
            return response()->json(['error' => 'Service unavailable'], 503);
        }

        $request->validate([
            'call_id' => 'required|string',
            'caller_message' => 'required|string',
            'language' => 'nullable|string|max:10',
        ]);

        $callId = $request->input('call_id');
        $callerMessage = $request->input('caller_message');
        $language = $request->input('language', 'en');

        $call = VoiceCall::where('id', $callId)->first();

        if (!$call) {
            return response()->json(['error' => 'Call not found'], 404);
        }

        $hid = $call->hospital_id;
        $settings = VoiceCallSetting::where('hospital_id', $hid)->first();

        // Update language used on the call
        if ($language && $call->language_used !== $language) {
            $call->language_used = $language;
            $call->language_detected = $language;
            $call->save();
        }

        // Load patient context
        $patient = $call->patient_id ? Patient::find($call->patient_id) : null;

        // Try to identify patient by caller number if not already matched
        if (!$patient && $call->caller_number) {
            $cleanNumber = preg_replace('/[^0-9]/', '', $call->caller_number);
            $patient = Patient::where('hospital_id', $hid)
                ->where(function ($q) use ($cleanNumber) {
                    $q->where('phone', $cleanNumber)
                        ->orWhere('phone', 'LIKE', '%' . substr($cleanNumber, -10));
                })
                ->first();

            if ($patient) {
                $call->patient_id = $patient->id;
                $call->save();
            }
        }

        // Build context
        $patientName = $patient?->name ?? 'there';
        $upcomingAppointment = null;
        $queuePosition = null;

        if ($patient) {
            $upcomingAppointment = Appointment::where('hospital_id', $hid)
                ->where('patient_id', $patient->id)
                ->where('slot_start', '>=', now())
                ->orderBy('slot_start')
                ->first();

            // Check queue position (today's checked_in appointments before theirs)
            $todayAppointment = Appointment::where('hospital_id', $hid)
                ->where('patient_id', $patient->id)
                ->whereDate('slot_start', Carbon::today())
                ->whereIn('status', ['checked_in'])
                ->first();

            if ($todayAppointment) {
                $queuePosition = Appointment::where('hospital_id', $hid)
                    ->where('doctor_id', $todayAppointment->doctor_id)
                    ->whereDate('slot_start', Carbon::today())
                    ->whereIn('status', ['checked_in'])
                    ->where('slot_start', '<=', $todayAppointment->slot_start)
                    ->count();
            }
        }

        // Save caller transcript
        $callerTimestamp = (int) (microtime(true) * 1000);
        VoiceCallTranscript::create([
            'id' => Str::uuid()->toString(),
            'voice_call_id' => $call->id,
            'speaker' => 'caller',
            'content' => $callerMessage,
            'language' => $language,
            'timestamp_ms' => $callerTimestamp,
        ]);

        // Keyword-based intent detection
        $messageLower = mb_strtolower($callerMessage);
        $responseText = '';
        $action = null;
        $endCall = false;

        // Emergency keywords — highest priority
        $emergencyKeywords = ['emergency', 'ambulance', 'heart attack', 'bleeding', 'accident', 'unconscious', 'dying', 'chest pain', 'stroke'];
        if ($this->containsAny($messageLower, $emergencyKeywords)) {
            $call->call_purpose = 'emergency';
            $call->sentiment = 'urgent';
            $call->save();

            $emergencyNumber = $settings?->emergency_transfer_number ?? '108';
            $responseText = "I understand this is an emergency. I'm transferring you to our emergency department right away. Please stay on the line.";
            $action = 'transfer_emergency';
            $call->transferred_to = $emergencyNumber;
            $call->transfer_reason = 'Emergency detected';
            $call->ai_handled = false;
            $call->save();
        }

        // Appointment booking
        elseif ($this->containsAny($messageLower, ['book', 'appointment', 'schedule', 'slot', 'doctor visit', 'see a doctor', 'consultation'])) {
            $call->call_purpose = 'appointment_booking';
            $call->save();

            if ($patient && $upcomingAppointment) {
                $apptDate = Carbon::parse($upcomingAppointment->slot_start)->format('l, F j');
                $apptTime = Carbon::parse($upcomingAppointment->slot_start)->format('g:i A');
                $responseText = "Hello {$patientName}. I see you already have an appointment on {$apptDate} at {$apptTime}. Would you like to book another appointment, or would you like to reschedule the existing one?";
            } else {
                $responseText = "I'd be happy to help you book an appointment. Could you tell me which department or type of doctor you'd like to see?";
                $action = 'book_appointment';
            }
        }

        // Schedule check
        elseif ($this->containsAny($messageLower, ['my appointment', 'when is my', 'check schedule', 'upcoming', 'next visit', 'appointment status'])) {
            $call->call_purpose = 'schedule_check';
            $call->save();

            if ($patient && $upcomingAppointment) {
                $apptDate = Carbon::parse($upcomingAppointment->slot_start)->format('l, F j');
                $apptTime = Carbon::parse($upcomingAppointment->slot_start)->format('g:i A');
                $responseText = "Hello {$patientName}. Your next appointment is on {$apptDate} at {$apptTime}. Is there anything else I can help you with?";
            } elseif ($patient) {
                $responseText = "Hello {$patientName}. I don't see any upcoming appointments for you. Would you like to book one?";
            } else {
                $responseText = "I'd like to check your appointment details. Could you please provide your name or phone number so I can look up your records?";
            }
        }

        // Lab results
        elseif ($this->containsAny($messageLower, ['lab result', 'test result', 'report', 'blood test', 'lab report', 'test report'])) {
            $call->call_purpose = 'lab_results';
            $call->save();

            if ($patient) {
                $responseText = "Hello {$patientName}. For the security of your medical information, lab results can be shared through our patient portal or during your next visit. Would you like me to transfer you to our lab department?";
            } else {
                $responseText = "I can help you with lab results. Could you please provide your name or patient ID so I can assist you?";
            }
        }

        // Queue status
        elseif ($this->containsAny($messageLower, ['queue', 'waiting', 'how long', 'wait time', 'turn', 'my position', 'token'])) {
            $call->call_purpose = 'queue_status';
            $call->save();

            if ($queuePosition !== null) {
                $estimatedWait = $queuePosition * 10; // rough estimate: 10 min per patient
                $responseText = "Hello {$patientName}. You are currently number {$queuePosition} in the queue. The estimated wait time is approximately {$estimatedWait} minutes.";
            } elseif ($patient) {
                $responseText = "Hello {$patientName}. You don't seem to be in any active queue right now. Would you like to check in for your appointment?";
            } else {
                $responseText = "I can check your queue status. Could you please provide your token number or name?";
            }
        }

        // Cancel appointment
        elseif ($this->containsAny($messageLower, ['cancel', 'cancel appointment', 'don\'t want', 'remove appointment'])) {
            $call->call_purpose = 'cancellation';
            $call->save();

            if ($patient && $upcomingAppointment) {
                $apptDate = Carbon::parse($upcomingAppointment->slot_start)->format('l, F j');
                $responseText = "I see you have an appointment on {$apptDate}. I'll transfer you to our reception desk to process the cancellation. One moment please.";
                $action = 'transfer_reception';
                $call->transferred_to = 'reception';
                $call->transfer_reason = 'Appointment cancellation request';
                $call->ai_handled = false;
                $call->save();
            } else {
                $responseText = "I don't see any upcoming appointments. Could you provide more details about which appointment you'd like to cancel?";
            }
        }

        // Talk to human
        elseif ($this->containsAny($messageLower, ['human', 'real person', 'operator', 'reception', 'transfer', 'speak to someone', 'talk to someone'])) {
            $call->call_purpose = $call->call_purpose ?? 'transfer_request';
            $call->save();

            $responseText = "Of course, I'll transfer you to our reception desk right away. Please hold on.";
            $action = 'transfer_reception';
            $call->transferred_to = 'reception';
            $call->transfer_reason = 'Caller requested human agent';
            $call->ai_handled = false;
            $call->save();
        }

        // Goodbye
        elseif ($this->containsAny($messageLower, ['bye', 'goodbye', 'thank you', 'thanks', 'that\'s all', 'done', 'hang up', 'nothing else'])) {
            $responseText = "Thank you for calling. Have a great day! Goodbye.";
            $endCall = true;
        }

        // General / greeting / unrecognized
        else {
            if ($this->containsAny($messageLower, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
                $greeting = $patient ? "Hello {$patientName}!" : "Hello!";
                $responseText = "{$greeting} Welcome to our hospital. I can help you with booking appointments, checking your schedule, queue status, or lab results. How can I assist you today?";
                $call->call_purpose = $call->call_purpose ?? 'general_inquiry';
                $call->save();
            } else {
                $responseText = "I'm sorry, I didn't quite understand that. I can help you with: booking an appointment, checking your schedule, queue status, or lab results. What would you like to do?";
                $call->call_purpose = $call->call_purpose ?? 'general_inquiry';
                $call->ai_confidence = 0.3;
                $call->save();
            }
        }

        // Set AI confidence if not already set
        if ($call->ai_confidence === null) {
            $call->ai_confidence = $action ? 0.85 : 0.75;
            $call->save();
        }

        // Save AI transcript
        $aiTimestamp = (int) (microtime(true) * 1000);
        VoiceCallTranscript::create([
            'id' => Str::uuid()->toString(),
            'voice_call_id' => $call->id,
            'speaker' => 'ai',
            'content' => $responseText,
            'language' => $language,
            'confidence' => $call->ai_confidence,
            'timestamp_ms' => $aiTimestamp,
        ]);

        return response()->json([
            'response_text' => $responseText,
            'action' => $action,
            'language' => $language,
            'end_call' => $endCall,
        ]);
    }

    /**
     * Manually initiate a callback from the dashboard.
     */
    public function initiateCallback(Request $request, string $id)
    {
        if (!$this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Service unavailable'], 503);
        }

        $callback = VoiceCallCallback::where('hospital_id', $this->hospitalId())
            ->where('id', $id)
            ->first();

        if (!$callback) {
            return response()->json(['success' => false, 'error' => 'Callback not found'], 404);
        }

        $callback->status = 'in_progress';
        $callback->attempts = $callback->attempts + 1;
        $callback->next_attempt_at = null;
        $callback->save();

        return response()->json([
            'success' => true,
            'message' => 'Callback initiated',
            'callback' => [
                'id' => $callback->id,
                'caller_number' => $callback->caller_number,
                'status' => $callback->status,
                'attempts' => $callback->attempts,
            ],
        ]);
    }

    /**
     * Analytics JSON for dashboard charts.
     */
    public function analyticsJson(Request $request)
    {
        if (!$this->tablesExist()) {
            return response()->json([]);
        }

        $hid = $this->hospitalId();
        $period = $request->input('period', '7d');

        $days = match ($period) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $startDate = Carbon::today()->subDays($days);

        $baseQuery = VoiceCall::where('hospital_id', $hid)
            ->where('created_at', '>=', $startDate);

        // Daily calls breakdown
        $dailyCalls = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->format('Y-m-d');

            $dayQuery = VoiceCall::where('hospital_id', $hid)
                ->whereDate('created_at', $date);

            $dailyCalls[] = [
                'date' => $dateStr,
                'total' => (clone $dayQuery)->count(),
                'ai_resolved' => (clone $dayQuery)->where('ai_handled', true)->whereNull('transferred_to')->where('status', 'completed')->count(),
                'transferred' => (clone $dayQuery)->whereNotNull('transferred_to')->count(),
            ];
        }

        // Peak hours
        $peakHours = [];
        $hourCounts = (clone $baseQuery)
            ->selectRaw("strftime('%H', created_at) as hour, count(*) as cnt")
            ->groupByRaw("strftime('%H', created_at)")
            ->pluck('cnt', 'hour');
        for ($h = 0; $h < 24; $h++) {
            $hourKey = str_pad($h, 2, '0', STR_PAD_LEFT);
            $peakHours[] = [
                'hour' => $h,
                'count' => $hourCounts[$hourKey] ?? 0,
            ];
        }

        // Language distribution
        $languageDistribution = (clone $baseQuery)
            ->whereNotNull('language_used')
            ->selectRaw('language_used as language, count(*) as cnt')
            ->groupBy('language_used')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($row) => ['language' => $row->language, 'count' => $row->cnt])
            ->toArray();

        // Purpose distribution
        $purposeDistribution = (clone $baseQuery)
            ->whereNotNull('call_purpose')
            ->selectRaw('call_purpose as purpose, count(*) as cnt')
            ->groupBy('call_purpose')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($row) => ['purpose' => $row->purpose, 'count' => $row->cnt])
            ->toArray();

        // Average duration trend
        $avgDurationTrend = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $avg = VoiceCall::where('hospital_id', $hid)
                ->whereDate('created_at', $date)
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds') ?? 0;

            $avgDurationTrend[] = [
                'date' => $date->format('Y-m-d'),
                'avg_seconds' => round($avg),
            ];
        }

        // Resolution rate
        $totalCompleted = (clone $baseQuery)->where('status', 'completed')->count();
        $aiResolved = (clone $baseQuery)->where('ai_handled', true)->whereNull('transferred_to')->where('status', 'completed')->count();
        $resolutionRate = $totalCompleted > 0 ? round(($aiResolved / $totalCompleted) * 100, 1) : 0;

        // Cost summary
        $totalCost = (clone $baseQuery)->sum('cost_amount') ?? 0;
        $totalCallsInPeriod = (clone $baseQuery)->count();
        $perCallAvg = $totalCallsInPeriod > 0 ? round($totalCost / $totalCallsInPeriod, 4) : 0;

        return response()->json([
            'daily_calls' => $dailyCalls,
            'peak_hours' => $peakHours,
            'language_distribution' => $languageDistribution,
            'purpose_distribution' => $purposeDistribution,
            'avg_duration_trend' => $avgDurationTrend,
            'resolution_rate' => $resolutionRate,
            'cost_summary' => [
                'total' => round($totalCost, 2),
                'per_call_avg' => round($perCallAvg, 4),
            ],
        ]);
    }

    /**
     * Check if a string contains any of the given keywords.
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}

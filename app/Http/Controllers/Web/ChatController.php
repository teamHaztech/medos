<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Multilingual\Services\LanguageDetector;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Triage\Services\SpecialtyMapper;
use App\Modules\Triage\Services\TriageService;
use App\Modules\AIReceptionist\Services\EmergencyDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $message = trim($request->input('message', ''));
        $phone = $request->input('phone', '');
        $sessionId = $request->input('session_id', '');

        // Input sanitization
        $message = mb_substr(strip_tags($message), 0, 500);
        $phone = preg_replace('/[^0-9+\-]/', '', mb_substr($phone, 0, 20));
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', mb_substr($sessionId, 0, 50));

        if (!$message) {
            return response()->json(['replies' => [['text' => 'Please type a message.']]]);
        }

        $defaultState = [
            'step' => 'greeting',
            'phone' => $phone,
            'language' => 'en',
            'patient_id' => null,
            'patient_name' => null,
            'complaint' => null,
            'urgency' => null,
            'specialty' => null,
            'doctor_id' => null,
            'doctor_name' => null,
        ];

        // Try phone-based session first (persistent across conversations)
        $state = null;
        if ($phone) {
            $phoneKey = 'chat_phone_' . preg_replace('/[^0-9]/', '', $phone);
            $state = cache()->get($phoneKey);
        }

        // Fall back to session-based
        if (!$state) {
            $state = cache()->get("chat_session_{$sessionId}", $defaultState);
        }

        // Resolve hospital: param → session → auth user → fallback
        $hospitalId = $request->input('hospital_id') ?: session('chat_hospital_id');
        if ($hospitalId) {
            $hospital = Hospital::where('id', $hospitalId)->where('is_active', true)->first();
        }
        if (!isset($hospital) || !$hospital) {
            $hospital = auth()->user()?->hospital
                ?? Hospital::where('is_active', true)->first();
        }
        if ($hospital) {
            session(['chat_hospital_id' => $hospital->id]);
        }
        $replies = [];

        // Detect language
        try {
            $detector = app(LanguageDetector::class);
            $langResult = $detector->detect($message);
            if ($langResult['confidence'] > 0.7) {
                $state['language'] = $langResult['language'];
            }
        } catch (\Throwable $e) {}

        $lang = $state['language'];

        // Wrap entire state machine in try-catch — NEVER leak errors to client
        try {
            // Emergency detection
            $emergencyDetector = new EmergencyDetector();
            $emergency = $emergencyDetector->detect($message, $lang);
            if ($emergency['is_emergency']) {
                $state['step'] = 'emergency';
                $replies[] = $this->t('emergency_alert', $lang);
                $replies[] = $this->t('emergency_instructions', $lang);
                cache()->put("chat_session_{$sessionId}", $state, 3600);
                return response()->json(['replies' => $this->wrap($replies), 'state' => $state]);
            }

            // Allow "menu", "hi", "hello", "start" to go back to main menu
            $lower = strtolower(trim($message));
            $menuWords = ['menu', 'hi', 'hello', 'start', 'main menu', 'back', 'reset', 'शुरू', 'मेनू', 'القائمة'];
            if (in_array($lower, $menuWords) && $state['patient_id'] && !in_array($state['step'], ['greeting', 'ask_phone', 'ask_name'])) {
                // Returning patient says hi — show appointments + menu
                $replies = $this->welcomeBackWithAppointments($state, $lang);
                cache()->put("chat_session_{$sessionId}", $state, 3600);
                if (!empty($state['phone'])) {
                    $phoneKey = 'chat_phone_' . preg_replace('/[^0-9]/', '', $state['phone']);
                    cache()->put($phoneKey, $state, 604800);
                }
                return response()->json(['replies' => $this->wrap($replies), 'state' => $state]);
            }

            switch ($state['step']) {
                case 'greeting':
                    $replies = $this->handleGreeting($message, $state, $lang, $hospital);
                    break;
                case 'ask_phone':
                    $replies = $this->handlePhone($message, $state, $lang, $hospital);
                    break;
                case 'ask_name':
                    $replies = $this->handleName($message, $state, $lang, $hospital);
                    break;
                case 'main_menu':
                    $replies = $this->handleMainMenu($message, $state, $lang, $hospital);
                    break;
                case 'ask_complaint':
                    $replies = $this->handleComplaint($message, $state, $lang, $hospital);
                    break;
                case 'show_doctors':
                    $replies = $this->handleDoctorSelection($message, $state, $lang, $hospital);
                    break;
                case 'pick_time':
                    $replies = $this->handlePickTime($message, $state, $lang, $hospital);
                    break;
                case 'confirm_booking':
                    $replies = $this->handleConfirmation($message, $state, $lang, $hospital);
                    break;
                case 'completed':
                    $state['step'] = 'main_menu';
                    $replies = $this->handleMainMenu($message, $state, $lang, $hospital);
                    break;
                case 'reschedule_pick':
                    $replies = $this->handleReschedulePick($message, $state, $lang, $hospital);
                    break;
                case 'reschedule_date':
                    $replies = $this->handleRescheduleDate($message, $state, $lang, $hospital);
                    break;
                case 'reschedule_confirm':
                    $replies = $this->handleRescheduleConfirm($message, $state, $lang, $hospital);
                    break;
                case 'cancel_pick':
                    $replies = $this->handleCancelPick($message, $state, $lang, $hospital);
                    break;
                case 'cancel_confirm':
                    $replies = $this->handleCancelConfirm($message, $state, $lang, $hospital);
                    break;
                default:
                    $replies[] = $this->t('confused', $lang);
                    $state['step'] = $state['patient_id'] ? 'main_menu' : 'greeting';
            }
        } catch (\Throwable $e) {
            // Log error server-side, never expose to client
            \Log::error('[ChatBot] Error: ' . $e->getMessage(), [
                'step' => $state['step'] ?? 'unknown',
                'message' => $message,
                'trace' => $e->getTraceAsString(),
            ]);
            $replies = ['Sorry, something went wrong. Please try again or type "hi" to start over.'];
            $state['step'] = $state['patient_id'] ? 'main_menu' : 'greeting';
        }

        // Save by session ID (1 hour)
        cache()->put("chat_session_{$sessionId}", $state, 3600);

        // Also save by phone for session persistence across conversations (7 days)
        if (!empty($state['phone'])) {
            $phoneKey = 'chat_phone_' . preg_replace('/[^0-9]/', '', $state['phone']);
            cache()->put($phoneKey, $state, 604800);
        }

        return response()->json(['replies' => $this->wrap($replies), 'state' => $state]);
    }

    // ---------------------------------------------------------------
    // Greeting & Identification
    // ---------------------------------------------------------------

    private function handleGreeting(string $msg, array &$state, string $lang, $hospital): array
    {
        // If user sends phone number directly at greeting, process it
        $digits = preg_replace('/[^0-9]/', '', $msg);
        if (strlen($digits) >= 10) {
            $state['step'] = 'ask_phone';
            return $this->handlePhone($msg, $state, $lang, $hospital);
        }

        // If user types their name, try to find them in DB
        $trimmed = trim($msg);
        if (strlen($trimmed) >= 3 && !is_numeric($trimmed) && !in_array(strtolower($trimmed), ['hi', 'hello', 'hey', 'start', 'menu', 'haan', 'ok'])) {
            $patient = Patient::where('name', 'like', '%' . $trimmed . '%')
                ->when($hospital, fn($q) => $q->where('hospital_id', $hospital->id))
                ->first();
            if ($patient) {
                $state['patient_id'] = $patient->id;
                $state['patient_name'] = $patient->name;
                $state['phone'] = preg_replace('/[^0-9]/', '', $patient->phone ?? '');
                return $this->welcomeBackWithAppointments($state, $lang);
            }
        }

        $state['step'] = 'ask_phone';
        $hospitalName = $hospital?->name ?? 'MedOS Hospital';
        return [
            $this->t('welcome', $lang, ['hospital' => $hospitalName]),
            $this->t('ask_phone', $lang),
        ];
    }

    private function handlePhone(string $msg, array &$state, string $lang, $hospital): array
    {
        // Check if input looks like ABHA number (14 digits or XX-XXXX-XXXX-XXXX format)
        $abhaClean = preg_replace('/[^0-9]/', '', $msg);
        if (strlen($abhaClean) === 14 || preg_match('/^\d{2}-?\d{4}-?\d{4}-?\d{4}$/', trim($msg))) {
            $abha = substr($abhaClean, 0, 14);
            $patient = Patient::where('abha_number', $abha)
                ->when($hospital, fn($q) => $q->where('hospital_id', $hospital->id))
                ->first();
            if ($patient) {
                $state['patient_id'] = $patient->id;
                $state['patient_name'] = $patient->name;
                $state['phone'] = preg_replace('/[^0-9]/', '', $patient->phone ?? '');
                $formattedAbha = substr($abha, 0, 2) . '-' . substr($abha, 2, 4) . '-' . substr($abha, 6, 4) . '-' . substr($abha, 10, 4);
                $greeting = $this->t('welcome_back', $lang, ['name' => $patient->name]) . "\n🏥 ABHA: {$formattedAbha}";
                return $this->welcomeBackWithAppointments($state, $lang, $greeting);
            }
        }

        $phone = preg_replace('/[^0-9]/', '', $msg);
        if (strlen($phone) < 10) {
            return [$this->t('invalid_phone', $lang)];
        }
        $phone = substr($phone, -10);
        $state['phone'] = $phone;

        // Check if patient exists (scoped to hospital)
        $patient = Patient::where('phone', '+91' . $phone)
                ->when($hospital, fn($q) => $q->where('hospital_id', $hospital->id))
                ->first()
            ?? Patient::where('phone', 'like', '%' . $phone)
                ->when($hospital, fn($q) => $q->where('hospital_id', $hospital->id))
                ->first();

        if ($patient) {
            $state['patient_id'] = $patient->id;
            $state['patient_name'] = $patient->name;
            return $this->welcomeBackWithAppointments($state, $lang);
        }

        $state['step'] = 'ask_name';
        return [$this->t('ask_name', $lang)];
    }

    private function handleName(string $msg, array &$state, string $lang, $hospital = null): array
    {
        $name = trim($msg);
        if (strlen($name) < 2) {
            return [$this->t('invalid_name', $lang)];
        }
        $state['patient_name'] = $name;

        // Create patient record in DB so they're recognized next time
        $phone = $state['phone'] ?? '';
        $fullPhone = strlen($phone) === 10 ? '+91' . $phone : (str_starts_with($phone, '+') ? $phone : '+91' . $phone);
        $hospitalId = $hospital?->id;

        if ($hospitalId && $phone) {
            // Check if patient already exists (edge case: cache lost but DB has them)
            $existing = Patient::where('phone', $fullPhone)
                ->where('hospital_id', $hospitalId)
                ->first()
                ?? Patient::where('phone', 'like', '%' . substr($phone, -10))
                    ->where('hospital_id', $hospitalId)
                    ->first();

            if ($existing) {
                $state['patient_id'] = $existing->id;
                $state['patient_name'] = $existing->name;
            } else {
                $patient = Patient::create([
                    'id' => Str::uuid()->toString(),
                    'hospital_id' => $hospitalId,
                    'name' => $name,
                    'phone' => $fullPhone,
                    'phone_verified' => false,
                    'language_preference' => $state['language'] ?? 'en',
                    'created_via' => 'whatsapp',
                ]);
                $state['patient_id'] = $patient->id;
            }
        }

        $state['step'] = 'main_menu';
        return [
            $this->t('nice_to_meet', $lang, ['name' => $name]),
            $this->t('main_menu', $lang),
        ];
    }

    // ---------------------------------------------------------------
    // Main Menu — Intent Router
    // ---------------------------------------------------------------

    private function handleMainMenu(string $msg, array &$state, string $lang, $hospital): array
    {
        $lower = strtolower(trim($msg));

        // Detect intent from message keywords
        $intent = $this->detectIntent($lower);

        switch ($intent) {
            case 'book':
                $state['step'] = 'ask_complaint';
                return [$this->t('ask_complaint', $lang)];

            case 'book_direct':
                // User typed a symptom/complaint directly — skip asking, go to doctor matching
                return $this->handleComplaint($msg, $state, $lang, $hospital);

            case 'reschedule':
                return $this->startReschedule($state, $lang);

            case 'cancel':
                return $this->startCancel($state, $lang);

            case 'status':
                return $this->showStatus($state, $lang);

            default:
                return [$this->t('main_menu', $lang)];
        }
    }

    private function detectIntent(string $lower): string
    {
        $trimmed = trim($lower);

        // Exact number match only (not partial)
        if ($trimmed === '1') return 'book';
        if ($trimmed === '2') return 'reschedule';
        if ($trimmed === '3') return 'cancel';
        if ($trimmed === '4') return 'status';

        // Book keywords
        $bookWords = ['book', 'new appointment', 'appointment', 'schedule', 'see doctor', 'visit', 'consult', 'checkup', 'check up', 'need doctor', 'want doctor', 'meet doctor', 'नया', 'बुक', 'अपॉइंटमेंट', 'दिखाना', 'दिखाओ', 'जाना', 'حجز', 'جديد'];
        foreach ($bookWords as $w) {
            if (str_contains($lower, $w)) return 'book';
        }

        // Reschedule keywords
        $rescheduleWords = ['reschedule', 'change date', 'change time', 'move', 'postpone', 'prepone', 'shift', 'different date', 'different time', 'another date', 'another time', 'kal', 'parso', 'बदलें', 'तारीख बदलो', 'समय बदलो', 'إعادة جدولة', 'تغيير'];
        foreach ($rescheduleWords as $w) {
            if (str_contains($lower, $w)) return 'reschedule';
        }

        // Cancel keywords
        $cancelWords = ['cancel', 'delete', 'remove', 'रद्द', 'कैंसल', 'إلغاء'];
        foreach ($cancelWords as $w) {
            if (str_contains($lower, $w)) return 'cancel';
        }

        // Status keywords
        $statusWords = ['status', 'check', 'my appointment', 'upcoming', 'when is', 'कब', 'स्टेटस', 'حالة', 'موعدي'];
        foreach ($statusWords as $w) {
            if (str_contains($lower, $w)) return 'status';
        }

        // Symptom detection — treat as booking intent
        $symptoms = ['fever', 'pain', 'headache', 'cough', 'cold', 'stomach', 'vomit', 'diarrhea', 'skin', 'rash', 'eye', 'ear', 'tooth', 'chest', 'breathing', 'diabetes', 'sugar', 'bp', 'blood pressure', 'bukhar', 'dard', 'sir dard', 'pet', 'khasi', 'zukam', 'ulti', 'sardi', 'حمى', 'ألم', 'صداع'];
        foreach ($symptoms as $s) {
            if (str_contains($lower, $s)) return 'book_direct';
        }

        // If message is long enough (likely a complaint), treat as booking
        if (strlen($trimmed) > 10) return 'book_direct';

        return 'unknown';
    }

    // ---------------------------------------------------------------
    // Check Status
    // ---------------------------------------------------------------

    private function showStatus(array &$state, string $lang): array
    {
        $upcoming = $this->getUpcomingAppointments($state['patient_id']);
        $missed = $this->getMissedAppointments($state['patient_id']);
        $replies = [];

        // Show missed appointments warning
        if ($missed->isNotEmpty()) {
            $missedList = "";
            foreach ($missed as $apt) {
                $doctor = Staff::find($apt->doctor_id);
                $date = Carbon::parse($apt->slot_start)->format('l, M d');
                $time = Carbon::parse($apt->slot_start)->format('g:i A');
                $docName = $doctor?->name ?? 'Doctor';
                $missedList .= "\n  ⚠️ {$docName} — {$date} at {$time}";
            }
            $missedMsg = $lang === 'hi'
                ? "⚠️ *छूटी हुई अपॉइंटमेंट:*{$missedList}\n\nकृपया नई अपॉइंटमेंट बुक करें।"
                : "⚠️ *Missed appointments:*{$missedList}\n\nPlease book a new appointment if needed.";
            $replies[] = $missedMsg;
        }

        // Show upcoming
        if ($upcoming->isNotEmpty()) {
            $list = "";
            foreach ($upcoming as $i => $apt) {
                $num = $i + 1;
                $doctor = Staff::find($apt->doctor_id);
                $date = Carbon::parse($apt->slot_start)->format('l, M d');
                $time = Carbon::parse($apt->slot_start)->format('g:i A');
                $token = $apt->notes ?? '-';
                $status = ucfirst(str_replace('_', ' ', $apt->status instanceof \BackedEnum ? $apt->status->value : $apt->status));

                // Time until appointment
                $minutesUntil = now()->diffInMinutes(Carbon::parse($apt->slot_start), false);
                $timeUntil = '';
                if ($minutesUntil > 0 && $minutesUntil <= 60) {
                    $timeUntil = " ⏰ *{$minutesUntil} min away!*";
                } elseif ($minutesUntil > 60) {
                    $hours = intdiv($minutesUntil, 60);
                    $timeUntil = " ⏰ in {$hours}h";
                }

                $docName = $doctor?->name ?? 'Doctor';
                $list .= "\n*{$num}.* {$docName}\n   📅 {$date} at {$time}{$timeUntil}\n   🎫 Token: {$token} | Status: {$status}\n";
            }
            $replies[] = $this->t('your_appointments', $lang) . $list;
        } elseif ($missed->isEmpty()) {
            $replies[] = $this->t('no_appointments', $lang);
        }

        $replies[] = $this->t('main_menu', $lang);
        $state['step'] = 'main_menu';
        return $replies;
    }

    // ---------------------------------------------------------------
    // Reschedule Flow
    // ---------------------------------------------------------------

    private function startReschedule(array &$state, string $lang): array
    {
        $appointments = $this->getUpcomingAppointments($state['patient_id']);

        if ($appointments->isEmpty()) {
            $state['step'] = 'main_menu';
            return [
                $this->t('no_appointments', $lang),
                $this->t('main_menu', $lang),
            ];
        }

        $state['upcoming_appointments'] = $appointments->map(fn ($a) => [
            'id' => $a->id,
            'doctor_id' => $a->doctor_id,
            'doctor_name' => Staff::find($a->doctor_id)?->name ?? 'Doctor',
            'slot_start' => $a->slot_start->toDateTimeString(),
            'slot_display' => $a->slot_start->format('l, M d') . ' at ' . $a->slot_start->format('g:i A'),
            'token' => $a->notes ?? '-',
        ])->values()->toArray();

        $list = "";
        foreach ($state['upcoming_appointments'] as $i => $apt) {
            $num = $i + 1;
            $list .= "\n*{$num}.* {$apt['doctor_name']} — {$apt['slot_display']} (Token: {$apt['token']})";
        }

        $state['step'] = 'reschedule_pick';
        return [
            $this->t('reschedule_pick', $lang) . $list,
            $this->t('type_number', $lang),
        ];
    }

    private function handleReschedulePick(string $msg, array &$state, string $lang, $hospital): array
    {
        $trimmed = trim($msg);
        $appointments = $state['upcoming_appointments'] ?? [];
        $apt = $this->smartPickAppointment($trimmed, $appointments);

        if (!$apt) {
            $list = implode(', ', array_map(fn($a) => $a['doctor_name'] . ' (' . $a['token'] . ')', $appointments));
            return ["I couldn't find that appointment. Reply with a *number* (1-" . count($appointments) . "), *doctor name*, or *token number*.\n\nYour appointments: {$list}"];
        }
        $state['reschedule_appointment_id'] = $apt['id'];
        $state['reschedule_doctor_id'] = $apt['doctor_id'];
        $state['reschedule_doctor_name'] = $apt['doctor_name'];
        $state['reschedule_old_slot'] = $apt['slot_display'];

        // Show available dates for this doctor (next 7 days)
        $doctor = Staff::find($apt['doctor_id']);
        $schedule = is_array($doctor->schedule) ? $doctor->schedule : json_decode($doctor->schedule ?? '{}', true);
        $availableDays = [];

        for ($d = 0; $d < 7; $d++) {
            $date = now()->addDays($d);
            $dayName = strtolower($date->format('l'));
            $blocks = $schedule[$dayName] ?? [];
            if (!empty($blocks)) {
                $availableDays[] = [
                    'date' => $date->toDateString(),
                    'display' => $date->format('l, M d'),
                    'day_name' => $dayName,
                ];
            }
        }

        if (empty($availableDays)) {
            $state['step'] = 'main_menu';
            return [
                $this->t('no_slots', $lang, ['doctor' => $apt['doctor_name']]),
                $this->t('main_menu', $lang),
            ];
        }

        $state['reschedule_available_days'] = $availableDays;
        $list = "";
        foreach ($availableDays as $i => $day) {
            $num = $i + 1;
            $list .= "\n*{$num}.* {$day['display']}";
        }

        $state['step'] = 'reschedule_date';
        return [
            $this->t('reschedule_choose_date', $lang, ['doctor' => $apt['doctor_name'], 'old_slot' => $apt['slot_display']]) . $list,
            $this->t('type_number', $lang),
        ];
    }

    private function handleRescheduleDate(string $msg, array &$state, string $lang, $hospital): array
    {
        $trimmed = trim($msg);
        $days = $state['reschedule_available_days'] ?? [];
        $chosenDay = $this->smartPickDay($trimmed, $days);

        if (!$chosenDay) {
            $list = implode(', ', array_map(fn($d, $i) => ($i+1) . '. ' . $d['display'], $days, array_keys($days)));
            return ["I couldn't understand that date. Reply with a *number* (1-" . count($days) . ") or type a day name like *Monday*, *tomorrow*, etc.\n\nAvailable: {$list}"];
        }
        $doctorId = $state['reschedule_doctor_id'];
        $doctor = Staff::find($doctorId);
        $schedule = is_array($doctor->schedule) ? $doctor->schedule : json_decode($doctor->schedule ?? '{}', true);
        $duration = $doctor->consultation_duration_default ?? 15;

        $blocks = $schedule[$chosenDay['day_name']] ?? [];
        $date = Carbon::parse($chosenDay['date']);

        $booked = \DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->whereDate('slot_start', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('id', '!=', $state['reschedule_appointment_id'])
            ->pluck('slot_start')
            ->map(fn ($s) => Carbon::parse($s)->format('H:i'))
            ->toArray();

        $slots = [];
        foreach ($blocks as $block) {
            $start = Carbon::parse($date->toDateString() . ' ' . $block['start']);
            $end = Carbon::parse($date->toDateString() . ' ' . $block['end']);
            while ($start->copy()->addMinutes($duration)->lte($end)) {
                $isPast = $date->isToday() && $start->lt(now());
                if (!$isPast && !in_array($start->format('H:i'), $booked)) {
                    $slots[] = [
                        'time' => $start->toDateTimeString(),
                        'display' => $start->format('g:i A'),
                    ];
                }
                $start->addMinutes($duration);
            }
        }

        if (empty($slots)) {
            $state['step'] = 'main_menu';
            return [
                $this->t('no_slots_date', $lang, ['date' => $chosenDay['display']]),
                $this->t('main_menu', $lang),
            ];
        }

        $state['reschedule_slots'] = $slots;
        $state['reschedule_date_display'] = $chosenDay['display'];

        $list = "";
        foreach ($slots as $i => $slot) {
            $num = $i + 1;
            $list .= "\n*{$num}.* {$slot['display']}";
        }

        $state['step'] = 'reschedule_confirm';
        return [
            $this->t('reschedule_choose_time', $lang, ['date' => $chosenDay['display']]) . $list,
            $this->t('type_number', $lang),
        ];
    }

    private function handleRescheduleConfirm(string $msg, array &$state, string $lang, $hospital): array
    {
        $trimmed = trim($msg);
        $slots = $state['reschedule_slots'] ?? [];
        $newSlot = $this->smartPickSlot($trimmed, $slots);

        if (!$newSlot) {
            return ["I couldn't understand that time. Reply with a *number* (1-" . count($slots) . ") or type a time like *3pm*, *10:30 AM*, or *earliest*."];
        }
        $newStart = Carbon::parse($newSlot['time']);

        // Validate slot is still in the future
        if ($newStart->isPast()) {
            $state['step'] = 'main_menu';
            return [
                "⚠️ This time slot has already passed. Please try again with a future time.",
                $this->t('main_menu', $lang),
            ];
        }

        $doctor = Staff::find($state['reschedule_doctor_id']);
        $duration = $doctor->consultation_duration_default ?? 15;

        // Update the appointment
        $appointment = Appointment::find($state['reschedule_appointment_id']);
        if (!$appointment) {
            $state['step'] = 'main_menu';
            return [$this->t('appointment_not_found', $lang), $this->t('main_menu', $lang)];
        }

        $oldSlot = $state['reschedule_old_slot'];
        $appointment->update([
            'rescheduled_from' => $appointment->slot_start->toDateTimeString(),
            'slot_start' => $newStart,
            'slot_end' => $newStart->copy()->addMinutes($duration),
        ]);

        $newDisplay = $state['reschedule_date_display'] . ' at ' . $newSlot['display'];

        $state['step'] = 'main_menu';
        return [
            $this->t('reschedule_done', $lang, [
                'doctor' => $state['reschedule_doctor_name'],
                'old_slot' => $oldSlot,
                'new_slot' => $newDisplay,
            ]),
            $this->t('main_menu', $lang),
        ];
    }

    // ---------------------------------------------------------------
    // Cancel Flow
    // ---------------------------------------------------------------

    private function startCancel(array &$state, string $lang): array
    {
        $appointments = $this->getUpcomingAppointments($state['patient_id']);

        if ($appointments->isEmpty()) {
            $state['step'] = 'main_menu';
            return [
                $this->t('no_appointments', $lang),
                $this->t('main_menu', $lang),
            ];
        }

        $state['cancel_appointments'] = $appointments->map(fn ($a) => [
            'id' => $a->id,
            'doctor_name' => Staff::find($a->doctor_id)?->name ?? 'Doctor',
            'slot_display' => $a->slot_start->format('l, M d') . ' at ' . $a->slot_start->format('g:i A'),
            'token' => $a->notes ?? '-',
        ])->values()->toArray();

        $list = "";
        foreach ($state['cancel_appointments'] as $i => $apt) {
            $num = $i + 1;
            $list .= "\n*{$num}.* {$apt['doctor_name']} — {$apt['slot_display']} (Token: {$apt['token']})";
        }

        $state['step'] = 'cancel_pick';
        return [
            $this->t('cancel_pick', $lang) . $list,
            $this->t('type_number', $lang),
        ];
    }

    private function handleCancelPick(string $msg, array &$state, string $lang, $hospital): array
    {
        $trimmed = trim($msg);
        $appointments = $state['cancel_appointments'] ?? [];
        $apt = $this->smartPickAppointment($trimmed, $appointments);

        if (!$apt) {
            $list = implode(', ', array_map(fn($a) => $a['doctor_name'] . ' (' . $a['token'] . ')', $appointments));
            return ["I couldn't find that appointment. Reply with a *number* (1-" . count($appointments) . "), *doctor name*, or *token number*.\n\nYour appointments: {$list}"];
        }
        $state['cancel_appointment_id'] = $apt['id'];
        $state['cancel_display'] = "{$apt['doctor_name']} — {$apt['slot_display']}";
        $state['step'] = 'cancel_confirm';

        return [
            $this->t('cancel_confirm_prompt', $lang, ['appointment' => $state['cancel_display']]),
        ];
    }

    private function handleCancelConfirm(string $msg, array &$state, string $lang, $hospital): array
    {
        $lower = strtolower(trim($msg));
        $yesWords = ['yes', 'y', 'ok', 'confirm', 'haan', 'ha', 'ji', 'theek', 'نعم'];
        $noWords = ['no', 'n', 'nahi', 'nhi', 'لا'];

        if (in_array($lower, $noWords)) {
            $state['step'] = 'main_menu';
            return [$this->t('cancel_aborted', $lang), $this->t('main_menu', $lang)];
        }

        if (!in_array($lower, $yesWords)) {
            return [$this->t('yes_or_no', $lang)];
        }

        $appointment = Appointment::find($state['cancel_appointment_id']);
        if (!$appointment) {
            $state['step'] = 'main_menu';
            return [$this->t('appointment_not_found', $lang), $this->t('main_menu', $lang)];
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => 'Cancelled by patient via WhatsApp',
        ]);

        $state['step'] = 'main_menu';
        return [
            $this->t('cancel_done', $lang, ['appointment' => $state['cancel_display']]),
            $this->t('main_menu', $lang),
        ];
    }

    // ---------------------------------------------------------------
    // Book Flow (existing)
    // ---------------------------------------------------------------

    private function handleComplaint(string $msg, array &$state, string $lang, $hospital): array
    {
        $state['complaint'] = $msg;

        // Map to specialty
        $mapper = new SpecialtyMapper();
        $state['specialty'] = $mapper->suggest($msg);

        // Find matching doctors
        $specialtyToDept = [
            'general_medicine' => ['General Medicine'], 'cardiology' => ['Cardiology', 'General Medicine'],
            'orthopedics' => ['Orthopedics', 'General Medicine'], 'pediatrics' => ['Pediatrics', 'General Medicine'],
            'gynecology' => ['Gynecology', 'General Medicine'], 'dermatology' => ['Dermatology', 'General Medicine'],
            'ent' => ['ENT', 'General Medicine'], 'dental' => ['Dental'],
            'gastroenterology' => ['General Medicine'], 'neurology' => ['General Medicine'],
            'ophthalmology' => ['General Medicine'], 'pulmonology' => ['General Medicine'],
            'endocrinology' => ['General Medicine'],
        ];

        $depts = $specialtyToDept[$state['specialty']] ?? ['General Medicine'];

        $doctors = Staff::where('hospital_id', $hospital->id)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin'])
            ->whereIn('department', $depts)
            ->get(['id', 'name', 'department', 'consultation_duration_default']);

        if ($doctors->isEmpty()) {
            $doctors = Staff::where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->whereIn('role', ['doctor', 'hospital_admin'])
                ->limit(3)->get(['id', 'name', 'department', 'consultation_duration_default']);
        }

        // Get queue counts
        $queueCounts = \DB::table('appointments')
            ->whereDate('slot_start', today())
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->selectRaw('doctor_id, count(*) as cnt')
            ->groupBy('doctor_id')
            ->pluck('cnt', 'doctor_id');

        $state['available_doctors'] = $doctors->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name, 'dept' => $d->department,
            'queue' => $queueCounts[$d->id] ?? 0,
            'wait' => ($queueCounts[$d->id] ?? 0) * ($d->consultation_duration_default ?? 15),
        ])->values()->toArray();

        $state['step'] = 'show_doctors';

        $replies = [$this->t('understood_complaint', $lang, ['complaint' => $msg])];

        $doctorList = "";
        foreach ($state['available_doctors'] as $i => $doc) {
            $num = $i + 1;
            $waitText = $doc['wait'] == 0 ? $this->t('no_wait', $lang) : $doc['wait'] . ' min wait';
            $doctorList .= "\n*{$num}.* {$doc['name']} ({$doc['dept']}) — {$waitText}";
        }

        $replies[] = $this->t('choose_doctor', $lang) . $doctorList;
        $replies[] = $this->t('type_number', $lang);

        return $replies;
    }

    private function handleDoctorSelection(string $msg, array &$state, string $lang, $hospital): array
    {
        $trimmed = trim($msg);
        $doctors = $state['available_doctors'] ?? [];
        $doc = null;

        // 1. Try number selection
        if (is_numeric($trimmed)) {
            $choice = intval($trimmed);
            if ($choice >= 1 && $choice <= count($doctors)) {
                $doc = $doctors[$choice - 1];
            }
        }

        // 2. Try exact/partial name match
        if (!$doc) {
            $search = strtolower($trimmed);
            // Remove common prefixes like "dr", "dr.", "doctor"
            $search = preg_replace('/^(dr\.?\s*|doctor\s*)/i', '', $search);
            $search = trim($search);

            foreach ($doctors as $d) {
                $dName = strtolower($d['name']);
                $dNameClean = preg_replace('/^(dr\.?\s*)/i', '', $dName);
                if (str_contains($dNameClean, $search) || str_contains($dName, $search) || str_contains(strtolower($d['dept']), $search)) {
                    $doc = $d;
                    break;
                }
            }
        }

        // 3. Fuzzy match — handle typos like "prya" → "Priya", "amit ptl" → "Amit Patel"
        if (!$doc && strlen($trimmed) >= 3) {
            $search = strtolower(preg_replace('/^(dr\.?\s*|doctor\s*)/i', '', $trimmed));
            $bestScore = PHP_INT_MAX;
            $bestDoc = null;

            foreach ($doctors as $d) {
                $dName = strtolower(preg_replace('/^(dr\.?\s*)/i', '', $d['name']));
                // Check each word in the doctor's name
                $nameWords = explode(' ', $dName);
                foreach ($nameWords as $word) {
                    $dist = levenshtein($search, $word);
                    // Accept if edit distance <= 2 (allows 2 typos)
                    if ($dist < $bestScore && $dist <= 2) {
                        $bestScore = $dist;
                        $bestDoc = $d;
                    }
                }
                // Also check full name similarity
                $fullDist = levenshtein($search, $dName);
                if ($fullDist < $bestScore && $fullDist <= 3) {
                    $bestScore = $fullDist;
                    $bestDoc = $d;
                }
                // Soundex match for phonetic similarity
                foreach ($nameWords as $word) {
                    if (soundex($search) === soundex($word) && strlen($search) >= 3) {
                        if (3 < $bestScore) {
                            $bestScore = 3;
                            $bestDoc = $d;
                        }
                    }
                }
            }
            $doc = $bestDoc;
        }

        if (!$doc) {
            $names = implode(', ', array_map(fn($d) => $d['name'], $doctors));
            return ["I couldn't find that doctor. Please reply with a number (1-" . count($doctors) . ") or type the doctor's name.\n\nAvailable: {$names}"];
        }

        $state['doctor_id'] = $doc['id'];
        $state['doctor_name'] = $doc['name'];

        // Build available slots for next 3 days and show to user
        $doctor = Staff::find($doc['id']);
        $schedule = is_array($doctor->schedule) ? $doctor->schedule : json_decode($doctor->schedule ?? '{}', true);
        $duration = $doctor->consultation_duration_default ?? 15;
        $availableSlots = [];

        for ($d = 0; $d < 7 && count($availableSlots) < 12; $d++) {
            $date = now()->addDays($d);
            $dayName = strtolower($date->format('l'));
            $blocks = $schedule[$dayName] ?? [];
            if (empty($blocks)) continue;

            $booked = \DB::table('appointments')
                ->where('doctor_id', $doc['id'])
                ->whereDate('slot_start', $date->toDateString())
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->pluck('slot_start')
                ->map(fn ($s) => Carbon::parse($s)->format('H:i'))
                ->toArray();

            foreach ($blocks as $block) {
                $start = Carbon::parse($date->toDateString() . ' ' . $block['start']);
                $end = Carbon::parse($date->toDateString() . ' ' . $block['end']);
                while ($start->copy()->addMinutes($duration)->lte($end)) {
                    $isPast = $d === 0 && $start->lt(now());
                    if (!$isPast && !in_array($start->format('H:i'), $booked)) {
                        $availableSlots[] = [
                            'start' => $start->toDateTimeString(),
                            'label' => ($d === 0 ? 'Today' : ($d === 1 ? 'Tomorrow' : $date->format('D, M d'))) . ' at ' . $start->format('g:i A'),
                        ];
                        if (count($availableSlots) >= 12) break 3;
                    }
                    $start->addMinutes($duration);
                }
            }
        }

        if (empty($availableSlots)) {
            return [$this->t('no_slots', $lang, ['doctor' => $doc['name']])];
        }

        $state['available_slots'] = $availableSlots;
        $state['step'] = 'pick_time';

        $slotList = "";
        foreach ($availableSlots as $i => $slot) {
            $num = $i + 1;
            $slotList .= "\n*{$num}.* {$slot['label']}";
        }

        return [
            "Great! Dr. {$doc['name']} ({$doc['dept']}) is available at these times:" . $slotList,
            "Reply with a *number* (1-" . count($availableSlots) . ") or type your preferred time (e.g. *3pm*, *tomorrow 10am*, *4:30 PM*).",
        ];
    }

    private function handlePickTime(string $msg, array &$state, string $lang, $hospital): array
    {
        $trimmed = trim($msg);
        $slots = $state['available_slots'] ?? [];
        $chosen = null;

        // 1. Try number selection
        if (is_numeric($trimmed)) {
            $choice = intval($trimmed);
            if ($choice >= 1 && $choice <= count($slots)) {
                $chosen = $slots[$choice - 1];
            }
        }

        // 2. Try parsing time from free text like "3pm", "3:30 PM", "tomorrow 10am", "15:00"
        if (!$chosen) {
            $lower = strtolower($trimmed);
            // Extract time pattern
            $timeMatch = null;
            if (preg_match('/(\d{1,2})[:\.]?(\d{2})?\s*(am|pm|AM|PM)?/', $trimmed, $m)) {
                $hour = intval($m[1]);
                $min = intval($m[2] ?? 0);
                $ampm = strtolower($m[3] ?? '');

                if ($ampm === 'pm' && $hour < 12) $hour += 12;
                if ($ampm === 'am' && $hour === 12) $hour = 0;
                // If no am/pm and hour <= 7, assume PM (clinic hours)
                if (!$ampm && $hour >= 1 && $hour <= 7) $hour += 12;

                $timeMatch = sprintf('%02d:%02d', $hour, $min);
            }

            // Detect day preference
            $dayOffset = null;
            if (str_contains($lower, 'today') || str_contains($lower, 'aaj') || str_contains($lower, 'now')) {
                $dayOffset = 0;
            } elseif (str_contains($lower, 'tomorrow') || str_contains($lower, 'kal')) {
                $dayOffset = 1;
            }

            if ($timeMatch) {
                // Find the closest matching slot
                $bestSlot = null;
                $bestDiff = PHP_INT_MAX;

                foreach ($slots as $slot) {
                    $slotTime = Carbon::parse($slot['start']);
                    $slotHM = $slotTime->format('H:i');

                    // If day preference given, filter by day
                    if ($dayOffset !== null) {
                        $targetDate = now()->addDays($dayOffset)->toDateString();
                        if ($slotTime->toDateString() !== $targetDate) continue;
                    }

                    // Find closest time
                    $reqMinutes = intval(substr($timeMatch, 0, 2)) * 60 + intval(substr($timeMatch, 3, 2));
                    $slotMinutes = $slotTime->hour * 60 + $slotTime->minute;
                    $diff = abs($reqMinutes - $slotMinutes);

                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestSlot = $slot;
                    }
                }

                // Accept if within 30 minutes of requested time
                if ($bestSlot && $bestDiff <= 30) {
                    $chosen = $bestSlot;
                }
            }

            // If only day mentioned without time, show slots for that day
            if (!$chosen && $dayOffset !== null && !$timeMatch) {
                $targetDate = now()->addDays($dayOffset)->toDateString();
                $daySlots = array_filter($slots, fn($s) => Carbon::parse($s['start'])->toDateString() === $targetDate);
                if (!empty($daySlots)) {
                    $daySlots = array_values($daySlots);
                    $slotList = "";
                    foreach ($daySlots as $i => $slot) {
                        $slotList .= "\n*" . ($i + 1) . ".* " . Carbon::parse($slot['start'])->format('g:i A');
                    }
                    $state['available_slots'] = $daySlots;
                    return ["Here are the available times for " . ($dayOffset === 0 ? 'today' : 'tomorrow') . ":" . $slotList . "\n\nReply with a number or type the time."];
                }
            }
        }

        // 3. Try "earliest" / "first" / "jaldi"
        if (!$chosen) {
            $lower = strtolower($trimmed);
            if (in_array($lower, ['earliest', 'first', 'asap', 'jaldi', 'pehla', 'any', 'koi bhi', '1st'])) {
                $chosen = $slots[0] ?? null;
            }
        }

        if (!$chosen) {
            return ["I couldn't understand that time. Please reply with:\n• A *number* (1-" . count($slots) . ") from the list\n• A *time* like *3pm* or *tomorrow 10am*\n• Or type *earliest* for the first available slot."];
        }

        $slotStart = Carbon::parse($chosen['start']);
        $state['slot_start'] = $chosen['start'];
        $state['slot_display'] = $chosen['label'];
        $state['step'] = 'confirm_booking';

        $doc = ['name' => $state['doctor_name'], 'dept' => '', 'wait' => 0];
        foreach ($state['available_doctors'] ?? [] as $d) {
            if ($d['id'] === $state['doctor_id']) { $doc = $d; break; }
        }

        return [
            $this->t('booking_summary', $lang, [
                'doctor' => $doc['name'],
                'dept' => $doc['dept'],
                'date' => $slotStart->format('l, M d'),
                'time' => $slotStart->format('g:i A'),
                'wait' => $doc['wait'],
                'complaint' => $state['complaint'],
            ]),
            $this->t('confirm_prompt', $lang),
        ];
    }

    private function handleConfirmation(string $msg, array &$state, string $lang, $hospital): array
    {
        $lower = strtolower(trim($msg));
        $yesWords = ['yes', 'y', 'ok', 'confirm', 'haan', 'ha', 'ji', 'theek', 'book', 'نعم'];
        $noWords = ['no', 'n', 'cancel', 'nahi', 'nhi', 'لا'];

        if (in_array($lower, $noWords)) {
            $state['step'] = 'main_menu';
            return [$this->t('cancelled', $lang), $this->t('main_menu', $lang)];
        }

        if (!in_array($lower, $yesWords)) {
            return [$this->t('yes_or_no', $lang)];
        }

        // Create patient if new
        if (!$state['patient_id']) {
            $patient = Patient::create([
                'id' => Str::uuid()->toString(),
                'hospital_id' => $hospital->id,
                'name' => $state['patient_name'],
                'phone' => '+91' . $state['phone'],
                'phone_verified' => false,
                'language_preference' => $lang,
                'created_via' => 'whatsapp',
            ]);
            $state['patient_id'] = $patient->id;
        }

        // Create encounter
        $encounter = Encounter::create([
            'id' => Str::uuid()->toString(),
            'hospital_id' => $hospital->id,
            'patient_id' => $state['patient_id'],
            'doctor_id' => $state['doctor_id'],
            'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'type' => 'consultation',
            'status' => 'booked',
            'channel' => 'whatsapp',
            'intake_data' => [
                'chief_complaint' => $state['complaint'],
                'source' => 'whatsapp_bot',
            ],
        ]);

        // Create appointment — with database-level lock to prevent double booking
        $slotStart = Carbon::parse($state['slot_start']);
        $doctor = Staff::find($state['doctor_id']);
        $duration = $doctor?->consultation_duration_default ?? 15;

        // Use DB transaction to ensure atomicity — check + create in one lock
        $slotTaken = false;
        \DB::beginTransaction();
        try {
            // Lock check: is slot still free?
            $slotTaken = Appointment::where('doctor_id', $state['doctor_id'])
                ->where('slot_start', $slotStart)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->exists();

        if ($slotTaken) {
            // Find next available slot automatically
            $schedule = is_array($doctor->schedule) ? $doctor->schedule : json_decode($doctor->schedule ?? '{}', true);
            $newSlot = null;

            for ($d = 0; $d < 7; $d++) {
                $date = now()->addDays($d);
                $dayName = strtolower($date->format('l'));
                $blocks = $schedule[$dayName] ?? [];
                if (empty($blocks)) continue;

                $booked = \DB::table('appointments')
                    ->where('doctor_id', $state['doctor_id'])
                    ->whereDate('slot_start', $date->toDateString())
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->pluck('slot_start')
                    ->map(fn ($s) => Carbon::parse($s)->format('H:i'))
                    ->toArray();

                foreach ($blocks as $block) {
                    $start = Carbon::parse($date->toDateString() . ' ' . $block['start']);
                    $end = Carbon::parse($date->toDateString() . ' ' . $block['end']);
                    while ($start->copy()->addMinutes($duration)->lte($end)) {
                        $isPast = $d === 0 && $start->lt(now());
                        if (!$isPast && !in_array($start->format('H:i'), $booked)) {
                            $newSlot = $start;
                            break 3;
                        }
                        $start->addMinutes($duration);
                    }
                }
            }

            if (!$newSlot) {
                \DB::rollBack();
                $state['step'] = 'main_menu';
                return [
                    "⚠️ Sorry, that slot was just taken by another patient and no other slots are available this week. Please try again later.",
                    $this->t('main_menu', $lang),
                ];
            }

            $slotStart = $newSlot;
            $state['slot_start'] = $newSlot->toDateTimeString();
            $state['slot_display'] = $newSlot->format('l, M d') . ' at ' . $newSlot->format('g:i A');
        }

        // Also validate slot is in the future
        if ($slotStart->isPast()) {
            \DB::rollBack();
            $state['step'] = 'show_doctors';
            return [
                "⚠️ This time has already passed. Let me find a new slot for you.",
                $this->t('main_menu', $lang),
            ];
        }

        $token = Appointment::generateToken($state['doctor_id'], $doctor?->department, $slotStart);

        Appointment::create([
            'id' => Str::uuid()->toString(),
            'hospital_id' => $hospital->id,
            'encounter_id' => $encounter->id,
            'patient_id' => $state['patient_id'],
            'doctor_id' => $state['doctor_id'],
            'status' => 'scheduled',
            'slot_start' => $slotStart,
            'slot_end' => $slotStart->copy()->addMinutes($duration),
            'predicted_duration_minutes' => $duration,
            'booking_source' => 'whatsapp',
            'notes' => $token,
        ]);

        \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('[ChatBot] Booking failed: ' . $e->getMessage());
            $state['step'] = 'main_menu';
            return [
                "⚠️ Booking failed. The slot may have just been taken. Please try again.",
                $this->t('main_menu', $lang),
            ];
        }

        $state['step'] = 'completed';
        $state['token'] = $token;

        return [
            $this->t('booking_confirmed', $lang, [
                'token' => $token,
                'doctor' => $state['doctor_name'],
                'slot' => $state['slot_display'],
                'name' => $state['patient_name'],
            ]),
            $this->t('arrival_instructions', $lang, ['token' => $token]),
        ];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Build welcome-back message with upcoming appointments + menu.
     */
    private function welcomeBackWithAppointments(array &$state, string $lang, string $extraGreeting = ''): array
    {
        $name = $state['patient_name'] ?? 'Patient';
        $replies = [];

        $greeting = $extraGreeting ?: $this->t('welcome_back', $lang, ['name' => $name]);
        $appointments = $this->getUpcomingAppointments($state['patient_id']);

        if ($appointments->isNotEmpty()) {
            $list = "";
            foreach ($appointments as $i => $apt) {
                $doctor = Staff::find($apt->doctor_id);
                $doctorName = $doctor?->name ?? 'Doctor';
                $date = Carbon::parse($apt->slot_start)->format('D, M d');
                $time = Carbon::parse($apt->slot_start)->format('g:i A');
                $token = $apt->notes ?? '-';
                $list .= "\n" . ($i + 1) . ". {$doctorName} — {$date} at {$time} (Token: {$token})";
            }
            $replies[] = $greeting . "\n\n📋 *Your upcoming appointments:*" . $list;
        } else {
            $replies[] = $greeting . "\n\nYou have no upcoming appointments.";
        }

        $state['step'] = 'main_menu';
        $replies[] = $this->t('main_menu', $lang);
        return $replies;
    }

    private function getUpcomingAppointments(?string $patientId)
    {
        if (!$patientId) return collect();

        // Auto-mark past appointments as no-show
        Appointment::where('patient_id', $patientId)
            ->where('slot_start', '<', now())
            ->whereIn('status', ['scheduled', 'confirmed', 'booked'])
            ->update(['status' => 'no_show']);

        return Appointment::where('patient_id', $patientId)
            ->where('slot_start', '>=', now())
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->orderBy('slot_start')
            ->get();
    }

    private function getMissedAppointments(?string $patientId)
    {
        if (!$patientId) return collect();

        return Appointment::where('patient_id', $patientId)
            ->where('slot_start', '<', now())
            ->where('status', 'no_show')
            ->where('slot_start', '>=', now()->subHours(24))
            ->orderByDesc('slot_start')
            ->get();
    }

    // ---------------------------------------------------------------
    // Smart input helpers — fuzzy matching for appointments, days, times
    // ---------------------------------------------------------------

    /**
     * Smart pick appointment by number, doctor name (fuzzy), or token.
     */
    private function smartPickAppointment(string $input, array $appointments): ?array
    {
        if (empty($appointments)) return null;

        // 1. Number selection
        if (is_numeric($input)) {
            $choice = intval($input);
            if ($choice >= 1 && $choice <= count($appointments)) {
                return $appointments[$choice - 1];
            }
        }

        $lower = strtolower($input);
        $lower = preg_replace('/^(dr\.?\s*|doctor\s*)/i', '', $lower);
        $lower = trim($lower);

        // 2. Token match (e.g. "PED-001", "ped001")
        $inputClean = preg_replace('/[^a-z0-9]/i', '', $lower);
        foreach ($appointments as $apt) {
            $tokenClean = preg_replace('/[^a-z0-9]/i', '', strtolower($apt['token'] ?? ''));
            if ($inputClean && $tokenClean && ($inputClean === $tokenClean || str_contains($tokenClean, $inputClean))) {
                return $apt;
            }
        }

        // 3. Exact/partial doctor name match
        foreach ($appointments as $apt) {
            $dName = strtolower(preg_replace('/^(dr\.?\s*)/i', '', $apt['doctor_name']));
            if (str_contains($dName, $lower) || str_contains(strtolower($apt['doctor_name']), $lower)) {
                return $apt;
            }
        }

        // 4. Fuzzy name match (levenshtein)
        $bestScore = PHP_INT_MAX;
        $bestApt = null;
        foreach ($appointments as $apt) {
            $dName = strtolower(preg_replace('/^(dr\.?\s*)/i', '', $apt['doctor_name']));
            foreach (explode(' ', $dName) as $word) {
                $dist = levenshtein($lower, $word);
                if ($dist < $bestScore && $dist <= 2) {
                    $bestScore = $dist;
                    $bestApt = $apt;
                }
            }
            if (soundex($lower) === soundex($dName) && strlen($lower) >= 3 && 3 < $bestScore) {
                $bestScore = 3;
                $bestApt = $apt;
            }
        }

        // 5. If only one appointment, accept anything vaguely affirmative
        if (!$bestApt && count($appointments) === 1) {
            $affirmative = ['yes', 'y', '1', 'ok', 'first', 'that', 'this', 'haan', 'ha', 'ji', 'woh', 'wahi'];
            if (in_array($lower, $affirmative)) {
                return $appointments[0];
            }
        }

        return $bestApt;
    }

    /**
     * Smart pick day by number, day name, or relative words (today/tomorrow/kal).
     */
    private function smartPickDay(string $input, array $days): ?array
    {
        if (empty($days)) return null;

        // 1. Number
        if (is_numeric(trim($input))) {
            $choice = intval($input);
            if ($choice >= 1 && $choice <= count($days)) {
                return $days[$choice - 1];
            }
        }

        $lower = strtolower(trim($input));

        // 2. Relative words
        $todayWords = ['today', 'aaj', 'abhi', 'now', 'आज'];
        $tomorrowWords = ['tomorrow', 'kal', 'कल', 'tmrw', 'tmr'];

        if (in_array($lower, $todayWords)) {
            $todayStr = now()->toDateString();
            foreach ($days as $day) {
                if ($day['date'] === $todayStr) return $day;
            }
        }
        if (in_array($lower, $tomorrowWords)) {
            $tomorrowStr = now()->addDay()->toDateString();
            foreach ($days as $day) {
                if ($day['date'] === $tomorrowStr) return $day;
            }
        }

        // 3. Day name match (monday, mon, tue, wednesday, etc.)
        $dayMap = [
            'mon' => 'monday', 'tue' => 'tuesday', 'wed' => 'wednesday',
            'thu' => 'thursday', 'fri' => 'friday', 'sat' => 'saturday', 'sun' => 'sunday',
            'somvar' => 'monday', 'mangal' => 'tuesday', 'budh' => 'wednesday',
            'guru' => 'thursday', 'shukra' => 'friday', 'shani' => 'saturday', 'ravi' => 'sunday',
        ];

        foreach ($dayMap as $short => $full) {
            if (str_starts_with($lower, $short) || str_contains($lower, $full)) {
                foreach ($days as $day) {
                    if ($day['day_name'] === $full) return $day;
                }
            }
        }

        // 4. Date match (e.g. "28", "May 28", "28 may")
        if (preg_match('/(\d{1,2})/', $lower, $m)) {
            $dayNum = intval($m[1]);
            foreach ($days as $day) {
                if (Carbon::parse($day['date'])->day === $dayNum) return $day;
            }
        }

        // 5. "earliest" / "first"
        if (in_array($lower, ['earliest', 'first', 'pehla', 'jaldi', 'any', 'koi bhi', '1st'])) {
            return $days[0];
        }

        return null;
    }

    /**
     * Smart pick time slot by number, time text, or keywords.
     */
    private function smartPickSlot(string $input, array $slots): ?array
    {
        if (empty($slots)) return null;

        // 1. Number
        if (is_numeric(trim($input))) {
            $choice = intval($input);
            if ($choice >= 1 && $choice <= count($slots)) {
                return $slots[$choice - 1];
            }
        }

        $lower = strtolower(trim($input));

        // 2. "earliest" / "first"
        if (in_array($lower, ['earliest', 'first', 'pehla', 'jaldi', 'any', 'asap', 'koi bhi', '1st'])) {
            return $slots[0];
        }

        // 3. "last" / "latest"
        if (in_array($lower, ['last', 'latest', 'aakhri'])) {
            return end($slots);
        }

        // 4. Parse time from text
        if (preg_match('/(\d{1,2})[:\.]?(\d{2})?\s*(am|pm|AM|PM)?/', $input, $m)) {
            $hour = intval($m[1]);
            $min = intval($m[2] ?? 0);
            $ampm = strtolower($m[3] ?? '');

            if ($ampm === 'pm' && $hour < 12) $hour += 12;
            if ($ampm === 'am' && $hour === 12) $hour = 0;
            if (!$ampm && $hour >= 1 && $hour <= 7) $hour += 12;

            $reqMinutes = $hour * 60 + $min;
            $bestSlot = null;
            $bestDiff = PHP_INT_MAX;

            foreach ($slots as $slot) {
                $t = Carbon::parse($slot['time'] ?? $slot['display'] ?? '');
                $slotMinutes = $t->hour * 60 + $t->minute;
                $diff = abs($reqMinutes - $slotMinutes);
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestSlot = $slot;
                }
            }

            if ($bestSlot && $bestDiff <= 30) return $bestSlot;
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Multilingual messages
    // ---------------------------------------------------------------

    private function t(string $key, string $lang, array $params = []): string
    {
        $messages = [
            'welcome' => [
                'en' => "Hello! 👋 Welcome to *{hospital}*.\nI'm your AI assistant. I can help you book, reschedule, or cancel appointments.",
                'hi' => "नमस्ते! 👋 *{hospital}* में आपका स्वागत है।\nमैं आपका AI सहायक हूं। मैं अपॉइंटमेंट बुक, रीशेड्यूल या कैंसल करने में मदद कर सकता हूं।",
                'ar' => "مرحباً! 👋 أهلاً بك في *{hospital}*.\nأنا مساعدك الذكي. يمكنني مساعدتك في حجز أو إعادة جدولة أو إلغاء المواعيد.",
            ],
            'ask_phone' => [
                'en' => "Please share your *10-digit phone number* to get started.\n\nYou can also share your *ABHA Health ID* / आभा नंबर भी भेज सकते हैं",
                'hi' => "शुरू करने के लिए कृपया अपना *10 अंकों का फोन नंबर* बताएं।\n\nआप अपना *आभा हेल्थ आईडी* भी भेज सकते हैं।",
                'ar' => "يرجى مشاركة *رقم هاتفك المكون من 10 أرقام* للبدء.\n\nيمكنك أيضاً مشاركة *معرف ABHA الصحي*.",
            ],
            'invalid_phone' => [
                'en' => "That doesn't look like a valid phone number. Please enter your *10-digit mobile number*.",
                'hi' => "यह सही फोन नंबर नहीं लग रहा। कृपया *10 अंकों का मोबाइल नंबर* दर्ज करें।",
                'ar' => "هذا لا يبدو رقم هاتف صالح. يرجى إدخال *رقم الهاتف المكون من 10 أرقام*.",
            ],
            'welcome_back' => [
                'en' => "Welcome back, *{name}*! 😊 Good to see you again.",
                'hi' => "वापस आने पर स्वागत है, *{name}*! 😊 आपसे दोबारा मिलकर अच्छा लगा।",
                'ar' => "مرحباً بعودتك، *{name}*! 😊 سعيد برؤيتك مرة أخرى.",
            ],
            'ask_name' => [
                'en' => "I see you're new here! What's your *full name*?",
                'hi' => "लगता है आप यहां नए हैं! आपका *पूरा नाम* क्या है?",
                'ar' => "يبدو أنك جديد هنا! ما *اسمك الكامل*؟",
            ],
            'invalid_name' => [
                'en' => "Please enter a valid name (at least 2 characters).",
                'hi' => "कृपया सही नाम दर्ज करें।",
                'ar' => "يرجى إدخال اسم صالح.",
            ],
            'nice_to_meet' => [
                'en' => "Nice to meet you, *{name}*! 🙏",
                'hi' => "आपसे मिलकर खुशी हुई, *{name}*! 🙏",
                'ar' => "تشرفنا، *{name}*! 🙏",
            ],
            'main_menu' => [
                'en' => "How can I help you today?\n\n*1.* 📅 Book new appointment\n*2.* 🔄 Reschedule appointment\n*3.* ❌ Cancel appointment\n*4.* 📋 Check my appointments\n\nReply with *1, 2, 3, or 4* — or just type what you need!",
                'hi' => "आज मैं आपकी कैसे मदद कर सकता हूं?\n\n*1.* 📅 नया अपॉइंटमेंट बुक करें\n*2.* 🔄 अपॉइंटमेंट रीशेड्यूल करें\n*3.* ❌ अपॉइंटमेंट कैंसल करें\n*4.* 📋 मेरी अपॉइंटमेंट देखें\n\n*1, 2, 3, या 4* भेजें — या बस बताएं आपको क्या चाहिए!",
                'ar' => "كيف يمكنني مساعدتك اليوم؟\n\n*1.* 📅 حجز موعد جديد\n*2.* 🔄 إعادة جدولة موعد\n*3.* ❌ إلغاء موعد\n*4.* 📋 التحقق من مواعيدي\n\nأرسل *1، 2، 3، أو 4* — أو اكتب ما تحتاجه!",
            ],
            'ask_complaint' => [
                'en' => "What problem are you facing? Please describe your symptoms.\n\n_For example: fever since 2 days, headache, stomach pain, etc._",
                'hi' => "आपको क्या तकलीफ है? कृपया अपने लक्षण बताएं।\n\n_जैसे: 2 दिन से बुखार, सिरदर्द, पेट दर्द, आदि_",
                'ar' => "ما المشكلة التي تواجهها؟ يرجى وصف أعراضك.\n\n_مثال: حمى منذ يومين، صداع، ألم في المعدة_",
            ],
            'understood_complaint' => [
                'en' => "I understand. You're experiencing: *{complaint}*\n\nLet me find the best doctor for you... 🔍",
                'hi' => "समझ गया। आपकी समस्या है: *{complaint}*\n\nआपके लिए सबसे अच्छा डॉक्टर ढूंढ रहा हूं... 🔍",
                'ar' => "فهمت. أنت تعاني من: *{complaint}*\n\nدعني أجد أفضل طبيب لك... 🔍",
            ],
            'choose_doctor' => [
                'en' => "Here are the available doctors:",
                'hi' => "ये डॉक्टर उपलब्ध हैं:",
                'ar' => "هؤلاء الأطباء المتاحون:",
            ],
            'type_number' => [
                'en' => "\nReply with the *number* (1, 2, 3...) to choose.",
                'hi' => "\nचुनने के लिए *नंबर* (1, 2, 3...) भेजें।",
                'ar' => "\nأرسل *الرقم* (1، 2، 3...) للاختيار.",
            ],
            'invalid_choice' => [
                'en' => "Please reply with a number between 1 and {max}.",
                'hi' => "कृपया 1 से {max} के बीच का नंबर भेजें।",
                'ar' => "يرجى الرد برقم بين 1 و {max}.",
            ],
            'no_wait' => ['en' => 'No wait!', 'hi' => 'कोई इंतज़ार नहीं!', 'ar' => 'بدون انتظار!'],
            'no_slots' => [
                'en' => "Sorry, no available slots for *{doctor}* in the next 7 days. Please try another doctor.",
                'hi' => "क्षमा करें, *{doctor}* के पास अगले 7 दिनों में कोई स्लॉट उपलब्ध नहीं है।",
                'ar' => "عذراً، لا توجد مواعيد متاحة لـ *{doctor}* خلال 7 أيام القادمة.",
            ],
            'no_slots_date' => [
                'en' => "Sorry, no available slots on *{date}*. Please try another date.",
                'hi' => "क्षमा करें, *{date}* को कोई स्लॉट उपलब्ध नहीं है।",
                'ar' => "عذراً، لا توجد مواعيد متاحة في *{date}*.",
            ],
            'booking_summary' => [
                'en' => "📋 *Booking Summary*\n\n🩺 Doctor: *{doctor}*\n🏥 Department: {dept}\n📅 Date: {date}\n⏰ Time: {time}\n💬 Complaint: {complaint}",
                'hi' => "📋 *बुकिंग सारांश*\n\n🩺 डॉक्टर: *{doctor}*\n🏥 विभाग: {dept}\n📅 तारीख: {date}\n⏰ समय: {time}\n💬 समस्या: {complaint}",
                'ar' => "📋 *ملخص الحجز*\n\n🩺 الطبيب: *{doctor}*\n🏥 القسم: {dept}\n📅 التاريخ: {date}\n⏰ الوقت: {time}\n💬 الشكوى: {complaint}",
            ],
            'confirm_prompt' => [
                'en' => "\nReply *Yes* to confirm or *No* to cancel.",
                'hi' => "\nपुष्टि करने के लिए *हां* या रद्द करने के लिए *नहीं* भेजें।",
                'ar' => "\nأرسل *نعم* للتأكيد أو *لا* للإلغاء.",
            ],
            'yes_or_no' => [
                'en' => "Please reply with *Yes* or *No*.",
                'hi' => "कृपया *हां* या *नहीं* में जवाब दें।",
                'ar' => "يرجى الرد بـ *نعم* أو *لا*.",
            ],
            'cancelled' => [
                'en' => "Booking cancelled. No worries! 🙂",
                'hi' => "बुकिंग रद्द कर दी गई। कोई बात नहीं! 🙂",
                'ar' => "تم إلغاء الحجز. لا مشكلة! 🙂",
            ],
            'booking_confirmed' => [
                'en' => "✅ *Appointment Confirmed!*\n\n🎫 Token: *{token}*\n👤 Patient: {name}\n🩺 Doctor: {doctor}\n📅 Slot: {slot}\n\nPlease arrive 10 minutes before your appointment.",
                'hi' => "✅ *अपॉइंटमेंट कन्फर्म!*\n\n🎫 टोकन: *{token}*\n👤 मरीज़: {name}\n🩺 डॉक्टर: {doctor}\n📅 समय: {slot}\n\nकृपया अपॉइंटमेंट से 10 मिनट पहले पहुंचें।",
                'ar' => "✅ *تم تأكيد الموعد!*\n\n🎫 التذكرة: *{token}*\n👤 المريض: {name}\n🩺 الطبيب: {doctor}\n📅 الموعد: {slot}\n\nيرجى الحضور قبل 10 دقائق من موعدك.",
            ],
            'arrival_instructions' => [
                'en' => "When you arrive, go to the kiosk and enter token *{token}* or your phone number to check in.",
                'hi' => "जब आप पहुंचें, कियोस्क पर जाएं और टोकन *{token}* या अपना फोन नंबर डालकर चेक-इन करें।",
                'ar' => "عند وصولك، اذهب إلى الكشك وأدخل التذكرة *{token}* أو رقم هاتفك لتسجيل الحضور.",
            ],
            'no_appointments' => [
                'en' => "You don't have any upcoming appointments.",
                'hi' => "आपकी कोई आगामी अपॉइंटमेंट नहीं है।",
                'ar' => "ليس لديك أي مواعيد قادمة.",
            ],
            'your_appointments' => [
                'en' => "📋 *Your Upcoming Appointments:*",
                'hi' => "📋 *आपकी आगामी अपॉइंटमेंट:*",
                'ar' => "📋 *مواعيدك القادمة:*",
            ],
            // --- Reschedule messages ---
            'reschedule_pick' => [
                'en' => "🔄 Which appointment would you like to reschedule?",
                'hi' => "🔄 कौन सी अपॉइंटमेंट रीशेड्यूल करनी है?",
                'ar' => "🔄 أي موعد تريد إعادة جدولته؟",
            ],
            'reschedule_choose_date' => [
                'en' => "📅 Rescheduling *{doctor}*\nCurrent slot: {old_slot}\n\nChoose a new date:",
                'hi' => "📅 *{doctor}* की अपॉइंटमेंट रीशेड्यूल\nवर्तमान समय: {old_slot}\n\nनई तारीख चुनें:",
                'ar' => "📅 إعادة جدولة *{doctor}*\nالموعد الحالي: {old_slot}\n\nاختر تاريخاً جديداً:",
            ],
            'reschedule_choose_time' => [
                'en' => "⏰ Available times on *{date}*:",
                'hi' => "⏰ *{date}* को उपलब्ध समय:",
                'ar' => "⏰ الأوقات المتاحة في *{date}*:",
            ],
            'reschedule_done' => [
                'en' => "✅ *Appointment Rescheduled!*\n\n🩺 Doctor: *{doctor}*\n📅 Old: {old_slot}\n📅 New: *{new_slot}*\n\nYour appointment has been moved successfully.",
                'hi' => "✅ *अपॉइंटमेंट रीशेड्यूल हो गई!*\n\n🩺 डॉक्टर: *{doctor}*\n📅 पुरानी: {old_slot}\n📅 नई: *{new_slot}*\n\nआपकी अपॉइंटमेंट सफलतापूर्वक बदल दी गई है।",
                'ar' => "✅ *تمت إعادة جدولة الموعد!*\n\n🩺 الطبيب: *{doctor}*\n📅 القديم: {old_slot}\n📅 الجديد: *{new_slot}*\n\nتم نقل موعدك بنجاح.",
            ],
            // --- Cancel messages ---
            'cancel_pick' => [
                'en' => "❌ Which appointment would you like to cancel?",
                'hi' => "❌ कौन सी अपॉइंटमेंट कैंसल करनी है?",
                'ar' => "❌ أي موعد تريد إلغاءه؟",
            ],
            'cancel_confirm_prompt' => [
                'en' => "Are you sure you want to cancel?\n\n🩺 {appointment}\n\nReply *Yes* to confirm or *No* to keep it.",
                'hi' => "क्या आप वाकई कैंसल करना चाहते हैं?\n\n🩺 {appointment}\n\nपुष्टि के लिए *हां* या रखने के लिए *नहीं* भेजें।",
                'ar' => "هل أنت متأكد من الإلغاء؟\n\n🩺 {appointment}\n\nأرسل *نعم* للتأكيد أو *لا* للإبقاء.",
            ],
            'cancel_done' => [
                'en' => "✅ Appointment cancelled successfully.\n\n🩺 {appointment}\n\nYou can always book a new one!",
                'hi' => "✅ अपॉइंटमेंट सफलतापूर्वक कैंसल हो गई।\n\n🩺 {appointment}\n\nआप कभी भी नई बुकिंग कर सकते हैं!",
                'ar' => "✅ تم إلغاء الموعد بنجاح.\n\n🩺 {appointment}\n\nيمكنك دائماً حجز موعد جديد!",
            ],
            'cancel_aborted' => [
                'en' => "OK, your appointment is safe! 🙂",
                'hi' => "ठीक है, आपकी अपॉइंटमेंट सुरक्षित है! 🙂",
                'ar' => "حسناً، موعدك آمن! 🙂",
            ],
            'appointment_not_found' => [
                'en' => "Sorry, I couldn't find that appointment. It may have been changed.",
                'hi' => "क्षमा करें, वह अपॉइंटमेंट नहीं मिली। शायद बदल गई हो।",
                'ar' => "عذراً، لم أتمكن من العثور على هذا الموعد.",
            ],
            'confused' => [
                'en' => "I didn't understand that. Let's start over. How can I help you today?",
                'hi' => "मुझे समझ नहीं आया। चलिए फिर से शुरू करते हैं। मैं आपकी कैसे मदद कर सकता हूं?",
                'ar' => "لم أفهم ذلك. لنبدأ من جديد. كيف يمكنني مساعدتك؟",
            ],
            'emergency_alert' => [
                'en' => "🚨 *EMERGENCY DETECTED!*\n\nThis sounds serious. Please go to the *Emergency Department* immediately!",
                'hi' => "🚨 *आपातकालीन स्थिति!*\n\nयह गंभीर लग रहा है। कृपया तुरंत *आपातकालीन विभाग* में जाएं!",
                'ar' => "🚨 *حالة طوارئ!*\n\nهذا يبدو خطيراً. يرجى الذهاب إلى *قسم الطوارئ* فوراً!",
            ],
            'emergency_instructions' => [
                'en' => "If you need an ambulance, call *108* (India) or *999* (UAE).\nOur emergency team has been alerted. 🏥",
                'hi' => "अगर एम्बुलेंस चाहिए तो *108* पर कॉल करें।\nहमारी आपातकालीन टीम को सूचित कर दिया गया है। 🏥",
                'ar' => "إذا كنت بحاجة لسيارة إسعاف، اتصل بـ *999*.\nتم تنبيه فريق الطوارئ. 🏥",
            ],
        ];

        $template = $messages[$key][$lang] ?? $messages[$key]['en'] ?? $key;

        foreach ($params as $k => $v) {
            $template = str_replace('{' . $k . '}', $v, $template);
        }

        return $template;
    }

    private function wrap(array $texts): array
    {
        return array_map(fn ($t) => ['text' => $t], $texts);
    }
}

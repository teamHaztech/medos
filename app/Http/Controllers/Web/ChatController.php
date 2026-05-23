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

        $hospital = Hospital::where('is_active', true)->first();
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
                $state['step'] = 'main_menu';
            }

            switch ($state['step']) {
                case 'greeting':
                    $replies = $this->handleGreeting($message, $state, $lang, $hospital);
                    break;
                case 'ask_phone':
                    $replies = $this->handlePhone($message, $state, $lang, $hospital);
                    break;
                case 'ask_name':
                    $replies = $this->handleName($message, $state, $lang);
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

        // Save by session ID
        cache()->put("chat_session_{$sessionId}", $state, 3600);

        // Also save by phone for session persistence across conversations
        if (!empty($state['phone'])) {
            $phoneKey = 'chat_phone_' . preg_replace('/[^0-9]/', '', $state['phone']);
            cache()->put($phoneKey, $state, 86400); // 24 hours
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
            $patient = Patient::where('abha_number', $abha)->first();
            if ($patient) {
                $state['patient_id'] = $patient->id;
                $state['patient_name'] = $patient->name;
                $state['phone'] = preg_replace('/[^0-9]/', '', $patient->phone ?? '');
                $state['step'] = 'main_menu';
                $formattedAbha = substr($abha, 0, 2) . '-' . substr($abha, 2, 4) . '-' . substr($abha, 6, 4) . '-' . substr($abha, 10, 4);
                return [
                    $this->t('welcome_back', $lang, ['name' => $patient->name]) . "\n🏥 ABHA: {$formattedAbha}",
                    $this->t('main_menu', $lang),
                ];
            }
        }

        $phone = preg_replace('/[^0-9]/', '', $msg);
        if (strlen($phone) < 10) {
            return [$this->t('invalid_phone', $lang)];
        }
        $phone = substr($phone, -10);
        $state['phone'] = $phone;

        // Check if patient exists
        $patient = Patient::where('phone', '+91' . $phone)->first()
            ?? Patient::where('phone', 'like', '%' . $phone)->first();

        if ($patient) {
            $state['patient_id'] = $patient->id;
            $state['patient_name'] = $patient->name;
            $state['step'] = 'main_menu';
            return [
                $this->t('welcome_back', $lang, ['name' => $patient->name]),
                $this->t('main_menu', $lang),
            ];
        }

        $state['step'] = 'ask_name';
        return [$this->t('ask_name', $lang)];
    }

    private function handleName(string $msg, array &$state, string $lang): array
    {
        $name = trim($msg);
        if (strlen($name) < 2) {
            return [$this->t('invalid_name', $lang)];
        }
        $state['patient_name'] = $name;
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
        $appointments = $this->getUpcomingAppointments($state['patient_id']);

        if ($appointments->isEmpty()) {
            $state['step'] = 'main_menu';
            return [
                $this->t('no_appointments', $lang),
                $this->t('main_menu', $lang),
            ];
        }

        $list = "";
        foreach ($appointments as $i => $apt) {
            $num = $i + 1;
            $doctor = Staff::find($apt->doctor_id);
            $doctorName = $doctor?->name ?? 'Doctor';
            $date = Carbon::parse($apt->slot_start)->format('l, M d');
            $time = Carbon::parse($apt->slot_start)->format('g:i A');
            $token = $apt->notes ?? '-';
            $status = ucfirst(str_replace('_', ' ', $apt->status instanceof \BackedEnum ? $apt->status->value : $apt->status));
            $list .= "\n*{$num}.* {$doctorName}\n   📅 {$date} at {$time}\n   🎫 Token: {$token} | Status: {$status}\n";
        }

        $state['step'] = 'main_menu';
        return [
            $this->t('your_appointments', $lang) . $list,
            $this->t('main_menu', $lang),
        ];
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
        $choice = intval(trim($msg));
        $appointments = $state['upcoming_appointments'] ?? [];

        if ($choice < 1 || $choice > count($appointments)) {
            return [$this->t('invalid_choice', $lang, ['max' => count($appointments)])];
        }

        $apt = $appointments[$choice - 1];
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
        $choice = intval(trim($msg));
        $days = $state['reschedule_available_days'] ?? [];

        if ($choice < 1 || $choice > count($days)) {
            return [$this->t('invalid_choice', $lang, ['max' => count($days)])];
        }

        $chosenDay = $days[$choice - 1];
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
        $choice = intval(trim($msg));
        $slots = $state['reschedule_slots'] ?? [];

        if ($choice < 1 || $choice > count($slots)) {
            return [$this->t('invalid_choice', $lang, ['max' => count($slots)])];
        }

        $newSlot = $slots[$choice - 1];
        $newStart = Carbon::parse($newSlot['time']);
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
        $choice = intval(trim($msg));
        $appointments = $state['cancel_appointments'] ?? [];

        if ($choice < 1 || $choice > count($appointments)) {
            return [$this->t('invalid_choice', $lang, ['max' => count($appointments)])];
        }

        $apt = $appointments[$choice - 1];
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

        // Try number selection first
        if (is_numeric($trimmed)) {
            $choice = intval($trimmed);
            if ($choice >= 1 && $choice <= count($doctors)) {
                $doc = $doctors[$choice - 1];
            }
        }

        // Try name match if number didn't work
        if (!$doc) {
            $search = strtolower($trimmed);
            foreach ($doctors as $d) {
                if (str_contains(strtolower($d['name']), $search) || str_contains(strtolower($d['dept']), $search)) {
                    $doc = $d;
                    break;
                }
            }
        }

        if (!$doc) {
            $names = implode(', ', array_map(fn($d) => $d['name'], $doctors));
            return ["Please reply with a number (1-" . count($doctors) . ") or type the doctor's name.\n\nAvailable: {$names}"];
        }
        $state['doctor_id'] = $doc['id'];
        $state['doctor_name'] = $doc['name'];

        // Find next available slot
        $doctor = Staff::find($doc['id']);
        $schedule = is_array($doctor->schedule) ? $doctor->schedule : json_decode($doctor->schedule ?? '{}', true);
        $duration = $doctor->consultation_duration_default ?? 15;
        $slotTime = null;
        $slotDate = null;

        for ($d = 0; $d < 7; $d++) {
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
                        $slotTime = $start->format('g:i A');
                        $slotDate = $date->format('l, M d');
                        $state['slot_start'] = $start->toDateTimeString();
                        break 3;
                    }
                    $start->addMinutes($duration);
                }
            }
        }

        if (!$slotTime) {
            return [$this->t('no_slots', $lang, ['doctor' => $doc['name']])];
        }

        $state['slot_display'] = "$slotDate at $slotTime";
        $state['step'] = 'confirm_booking';

        return [
            $this->t('booking_summary', $lang, [
                'doctor' => $doc['name'],
                'dept' => $doc['dept'],
                'date' => $slotDate,
                'time' => $slotTime,
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

        // Create appointment
        $slotStart = Carbon::parse($state['slot_start']);
        $duration = Staff::find($state['doctor_id'])?->consultation_duration_default ?? 15;
        $deptPrefix = strtoupper(substr(str_replace(' ', '', Staff::find($state['doctor_id'])?->department ?? 'GEN'), 0, 3));
        $todayCount = Appointment::where('doctor_id', $state['doctor_id'])->whereDate('slot_start', $slotStart->toDateString())->count();
        $token = $deptPrefix . '-' . str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);

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
            $this->t('main_menu', $lang),
        ];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getUpcomingAppointments(?string $patientId)
    {
        if (!$patientId) return collect();

        return Appointment::where('patient_id', $patientId)
            ->where('slot_start', '>=', now())
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->orderBy('slot_start')
            ->get();
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
                'en' => "When you arrive, go to the kiosk and enter token *{token}* or your phone number to check in. See you soon! 👋",
                'hi' => "जब आप पहुंचें, कियोस्क पर जाएं और टोकन *{token}* या अपना फोन नंबर डालकर चेक-इन करें। जल्द मिलते हैं! 👋",
                'ar' => "عند وصولك، اذهب إلى الكشك وأدخل التذكرة *{token}* أو رقم هاتفك لتسجيل الحضور. نراك قريباً! 👋",
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

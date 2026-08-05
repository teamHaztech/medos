<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Triage\Services\SpecialtyMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    /**
     * Resolve the current kiosk hospital from session, query param, or fallback.
     */
    private function resolveHospital(?Request $request = null): ?Hospital
    {
        // 1. Query param ?hospital=slug sets session
        if ($request && $request->has('hospital')) {
            $hospital = Hospital::where('slug', $request->get('hospital'))
                ->where('is_active', true)->first();
            if ($hospital) {
                session(['kiosk_hospital_id' => $hospital->id]);
                return $hospital;
            }
        }

        // 2. Session
        if (session('kiosk_hospital_id')) {
            $hospital = Hospital::where('id', session('kiosk_hospital_id'))
                ->where('is_active', true)->first();
            if ($hospital) return $hospital;
        }

        // 3. Fallback: if only one active hospital, use it
        $active = Hospital::where('is_active', true)->get();
        if ($active->count() === 1) {
            session(['kiosk_hospital_id' => $active->first()->id]);
            return $active->first();
        }

        // Multiple hospitals and none selected — return null (show selector)
        return null;
    }

    public function index(Request $request)
    {
        // Allow resetting hospital selection
        if ($request->has('reset_hospital')) {
            session()->forget('kiosk_hospital_id');
            return redirect()->route('kiosk.index');
        }

        $hospital = $this->resolveHospital($request);
        $hospitals = Hospital::where('is_active', true)->orderBy('name')->get();
        $showSelector = !$hospital && $hospitals->count() > 1;

        return view('kiosk.index', compact('hospital', 'hospitals', 'showSelector'));
    }

    public function selectHospital(Request $request)
    {
        $request->validate(['hospital_id' => 'required|exists:hospitals,id']);
        session(['kiosk_hospital_id' => $request->hospital_id]);
        return redirect()->route('kiosk.index');
    }

    public function checkin()
    {
        return view('kiosk.checkin');
    }

    public function processCheckin(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'min:2'],
        ]);

        $input = trim($request->input('token'));
        $hospital = $this->resolveHospital($request);
        $hospitalId = $hospital?->id;
        $activeStatuses = ['scheduled', 'confirmed', 'checked_in', 'in_progress'];

        // Build a matcher that prefers today's appointment but falls back to any
        // active appointment, so a valid token/phone/name always resolves even if
        // the visit was booked for another day. The patient is pulled into today's
        // queue below on arrival.
        $match = function (callable $constrain) use ($hospitalId, $activeStatuses) {
            $build = function (bool $todayOnly) use ($hospitalId, $activeStatuses, $constrain) {
                $q = Appointment::query()
                    ->whereIn('status', $activeStatuses)
                    ->when($hospitalId, fn($qq) => $qq->where('hospital_id', $hospitalId))
                    ->when($todayOnly, fn($qq) => $qq->whereDate('slot_start', today()))
                    ->with(['patient', 'doctor'])
                    ->orderBy('slot_start');
                $constrain($q);
                return $q;
            };

            return $build(true)->first() ?? $build(false)->first();
        };

        // Try token (stored in notes field)
        $appointment = $match(fn($q) => $q->where('notes', $input));

        // Try phone number (with or without +91 prefix)
        if (!$appointment) {
            $phoneCleaned = preg_replace('/[^0-9]/', '', $input);
            if (strlen($phoneCleaned) >= 10) {
                $appointment = $match(fn($q) => $q->whereHas('patient', fn($p) =>
                    $p->where('phone', 'like', '%' . substr($phoneCleaned, -10))
                ));
            }
        }

        // Try patient name (partial match)
        if (!$appointment && strlen($input) > 2 && !is_numeric($input)) {
            $appointment = $match(fn($q) => $q->whereHas('patient', fn($p) =>
                $p->where('name', 'like', '%' . $input . '%')
            ));
        }

        // No doctor appointment matched — try a lab booking (orders carry LAB- tokens).
        if (!$appointment) {
            $phoneCleaned = preg_replace('/[^0-9]/', '', $input);
            $labOrders = \App\Modules\Core\Models\Order::query()
                ->whereIn('type', ['lab', 'imaging', 'procedure'])
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->when($hospitalId, fn($q) => $q->where('hospital_id', $hospitalId))
                ->where(function ($q) use ($input, $phoneCleaned) {
                    $q->where('notes', $input);
                    if (strlen($phoneCleaned) >= 10) {
                        $q->orWhereHas('patient', fn($p) => $p->where('phone', 'like', '%' . substr($phoneCleaned, -10)));
                    }
                    if (strlen($input) > 2 && !is_numeric($input)) {
                        $q->orWhereHas('patient', fn($p) => $p->where('name', 'like', '%' . $input . '%'));
                    }
                })
                ->with('patient')
                ->orderByDesc('created_at')
                ->get();

            if ($labOrders->isNotEmpty()) {
                // A booking is one token spanning one or more type-orders; show that group.
                $token = $labOrders->first()->notes;
                $group = $token ? $labOrders->where('notes', $token) : $labOrders->take(1);
                $tests = $group->flatMap(fn($o) => collect($o->items)->pluck('name'))->filter()->values()->all();
                $slot = $group->first()->scheduled_for;

                return response()->json([
                    'success'     => true,
                    'type'        => 'lab',
                    'patientName' => $group->first()->patient?->name ?? 'Patient',
                    'token'       => $token ?? 'LAB',
                    'tests'       => $tests,
                    'when'        => $slot ? $slot->format('D, M d \a\t g:i A') : 'Walk-in today',
                    'room'        => $this->hospitalArea($group->first()->hospital_id, 'lab', 'Sample Collection Counter'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No booking found. Try your token number, phone number, or name.',
            ], 404);
        }

        // The patient is physically here: check them in and pull the visit into
        // today's live queue (so it shows on the doctor's queue) if it was booked
        // for another day.
        $aptStatus = is_object($appointment->status) ? $appointment->status->value : $appointment->status;
        $updates = [];
        if (in_array($aptStatus, ['scheduled', 'confirmed'])) {
            $updates['status'] = 'checked_in';
            $updates['check_in_time'] = now();
        }
        if (!$appointment->slot_start || !$appointment->slot_start->isToday()) {
            $duration = $appointment->doctor?->consultation_duration_default ?? 15;
            $updates['slot_start'] = now();
            $updates['slot_end'] = now()->copy()->addMinutes($duration);
        }
        if (!empty($updates)) {
            $appointment->update($updates);
        }

        // Count patients ahead
        $ahead = Appointment::where('doctor_id', $appointment->doctor_id)
            ->whereDate('slot_start', today())
            ->where('slot_start', '<', $appointment->slot_start)
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->count();

        $consultationMinutes = $appointment->doctor?->consultation_duration_default ?? 15;
        $estimatedWait = $ahead * $consultationMinutes;

        return response()->json([
            'success'       => true,
            'patientName'   => $appointment->patient?->name ?? 'Patient',
            'queuePosition' => $ahead + 1,
            'doctorName'    => $appointment->doctor?->name ?? 'Doctor',
            'department'    => $appointment->doctor?->department ?? 'General',
            'token'         => $appointment->notes ?? 'N/A',
            'estimatedWait' => $estimatedWait . ' minutes',
            'room'          => $this->hospitalArea($appointment->hospital_id, 'waiting', 'Floor 2, Waiting Area'),
        ]);
    }

    /** Resolve a hospital's configured patient area (waiting|lab), falling back to a default. */
    private function hospitalArea(?string $hospitalId, string $type, string $default): string
    {
        if (! $hospitalId) {
            return $default;
        }
        $hospital = \App\Modules\Core\Models\Hospital::find($hospitalId);
        $cfg = is_array($hospital?->config) ? $hospital->config : json_decode($hospital?->config ?? '{}', true);
        $val = trim((string) (($cfg['areas'] ?? [])[$type] ?? ''));

        return $val !== '' ? $val : $default;
    }

    public function register()
    {
        return view('kiosk.register');
    }

    public function doctors(Request $request)
    {
        $hospital = $this->resolveHospital($request);
        if (!$hospital) {
            return response()->json([]);
        }

        $doctors = Staff::where('hospital_id', $hospital->id)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->orderBy('department')
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'specialization'])
            ->map(fn ($d) => [
                'id'         => $d->id,
                'name'       => $d->name,
                'department' => $d->department,
                'specialization' => $d->specialization,
            ]);

        $departments = $doctors->pluck('department')->unique()->values();

        return response()->json([
            'doctors'     => $doctors,
            'departments' => $departments,
        ]);
    }

    public function matchDoctors(Request $request)
    {
        $complaint = $request->get('complaint', 'general');
        $hospital = $this->resolveHospital($request);
        if (!$hospital) return response()->json([]);

        // Map complaint to specialty
        $mapper = new SpecialtyMapper();
        $specialty = $mapper->suggest($complaint);

        // Build mapping: specialty -> department names that could handle it
        $specialtyToDept = [
            'general_medicine'    => ['General Medicine'],
            'cardiology'          => ['Cardiology', 'General Medicine'],
            'orthopedics'         => ['Orthopedics', 'General Medicine'],
            'pediatrics'          => ['Pediatrics', 'General Medicine'],
            'gynecology'          => ['Gynecology', 'General Medicine'],
            'dermatology'         => ['Dermatology', 'General Medicine'],
            'ent'                 => ['ENT', 'General Medicine'],
            'dental'              => ['Dental'],
            'nutrition'           => ['Clinical Nutrition'],
            'gastroenterology'    => ['General Medicine', 'Gastroenterology'],
            'neurology'           => ['General Medicine', 'Neurology'],
            'ophthalmology'       => ['General Medicine', 'Ophthalmology'],
            'pulmonology'         => ['General Medicine', 'Pulmonology'],
            'endocrinology'       => ['General Medicine', 'Endocrinology'],
            'nephrology/urology'  => ['General Medicine', 'Nephrology', 'Urology'],
            'psychiatry'          => ['General Medicine', 'Psychiatry'],
            'oncology'            => ['General Medicine', 'Oncology'],
        ];

        $matchingDepts = $specialtyToDept[$specialty] ?? ['General Medicine'];

        // Get all doctors, grouped by relevance
        $allDoctors = Staff::where('hospital_id', $hospital->id)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->orderBy('department')->orderBy('name')
            ->get(['id', 'name', 'department', 'specialization', 'consultation_duration_default']);

        // Count today's queue per doctor
        $queueCounts = \DB::table('appointments')
            ->whereDate('slot_start', today())
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->selectRaw('doctor_id, count(*) as cnt')
            ->groupBy('doctor_id')
            ->pluck('cnt', 'doctor_id');

        $recommended = [];
        $others = [];

        foreach ($allDoctors as $doc) {
            $isMatch = in_array($doc->department, $matchingDepts);
            $entry = [
                'id'         => $doc->id,
                'name'       => $doc->name,
                'department' => $doc->department,
                'queue'      => $queueCounts[$doc->id] ?? 0,
                'duration'   => $doc->consultation_duration_default ?? 15,
            ];

            if ($isMatch) {
                $recommended[] = $entry;
            } else {
                $others[] = $entry;
            }
        }

        return response()->json([
            'specialty'   => $specialty,
            'recommended' => $recommended,
            'others'      => $others,
        ]);
    }

    public function checkPhone(Request $request)
    {
        $phone = preg_replace('/[^0-9]/', '', $request->get('phone', ''));
        if (strlen($phone) === 10) {
            $phone = '+91' . $phone;
        }

        $hospital = $this->resolveHospital($request);
        $hospitalId = $hospital?->id;

        $patient = Patient::where('phone', $phone)
                ->when($hospitalId, fn($q) => $q->where('hospital_id', $hospitalId))
                ->first()
            ?? Patient::where('phone', 'like', '%' . substr($phone, -10))
                ->when($hospitalId, fn($q) => $q->where('hospital_id', $hospitalId))
                ->first();

        if ($patient) {
            return response()->json([
                'exists' => true,
                'name'   => $patient->name,
                'gender' => $patient->gender,
                'phone'  => $patient->phone,
            ]);
        }

        return response()->json(['exists' => false]);
    }

    public function verifyAbha(Request $request)
    {
        $abha = preg_replace('/[^0-9]/', '', $request->get('abha', ''));

        if (strlen($abha) !== 14) {
            return response()->json([
                'exists' => false,
                'message' => 'Invalid ABHA number. Must be 14 digits.',
            ], 422);
        }

        // Search in patients table (scoped to hospital)
        $hospital = $this->resolveHospital($request);
        $hospitalId = $hospital?->id;
        $patient = Patient::where('abha_number', $abha)
            ->when($hospitalId, fn($q) => $q->where('hospital_id', $hospitalId))
            ->first();

        if ($patient) {
            $age = $patient->date_of_birth
                ? \Carbon\Carbon::parse($patient->date_of_birth)->age
                : $patient->age_approximate;

            return response()->json([
                'exists'  => true,
                'name'    => $patient->name,
                'phone'   => $patient->phone,
                'gender'  => $patient->gender,
                'age'     => $age,
                'profile' => [
                    'name'   => $patient->name,
                    'phone'  => $patient->phone,
                    'gender' => $patient->gender,
                ],
            ]);
        }

        // Mock ABHA verification service (in production, call ABDM API)
        // For now, return not found so user can proceed with manual entry
        return response()->json([
            'exists'  => false,
            'message' => 'ABHA number not found in our records. Please register with phone number.',
        ]);
    }

    public function processRegister(Request $request)
    {
        // Normalize gender: a returning patient may carry a stored value outside the
        // selectable set (e.g. the DB default 'unknown', or a value from the chat bot).
        // Map anything that isn't male/female/other to null so it passes validation;
        // it is stored as 'unknown' below.
        $g = strtolower(trim((string) $request->input('gender')));
        $request->merge(['gender' => in_array($g, ['male', 'female', 'other'], true) ? $g : null]);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'required|string|min:4|max:15',
            'age'          => 'nullable|integer|min:0|max:120',
            'gender'       => 'nullable|in:male,female,other',
            'complaint'    => 'required|string|max:1000',
            'language'     => 'nullable|in:en,hi,ar',
            'is_emergency' => 'nullable|boolean',
            'doctor_id'    => 'nullable|string|exists:staff,id',
            'department'   => 'nullable|string|max:100',
            'abha_number'  => 'nullable|string|size:14',
        ]);

        // Use the kiosk's selected hospital
        $hospital = $this->resolveHospital($request);
        if (!$hospital) {
            return response()->json(['success' => false, 'message' => 'Hospital not selected. Please go back and select a hospital.'], 500);
        }

        // Normalize phone
        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);
        if (!str_starts_with($phone, '+')) {
            $phone = strlen($phone) === 10 ? '+91' . $phone : '+' . $phone;
        }

        // Find or create patient. Include soft-deleted rows: a previously-deleted
        // patient still holds the unique (hospital_id, phone) slot, so restore it
        // instead of inserting a duplicate (which would violate the constraint).
        $patient = Patient::withTrashed()->where('hospital_id', $hospital->id)->where('phone', $phone)->first();
        if ($patient && method_exists($patient, 'trashed') && $patient->trashed()) {
            $patient->restore();
        }

        // Update existing patient's ABHA if provided and not already set
        if ($patient && !empty($validated['abha_number']) && !$patient->abha_number) {
            $patient->update(['abha_number' => $validated['abha_number']]);
        }

        if (!$patient) {
            $patientData = [
                'id'                  => Str::uuid()->toString(),
                'hospital_id'        => $hospital->id,
                'name'               => $validated['name'],
                'phone'              => $phone,
                'phone_verified'     => false,
                'age_approximate'    => $validated['age'] ?? null,
                'gender'             => $validated['gender'] ?? 'unknown',
                'language_preference' => $validated['language'] ?? 'en',
                'created_via'        => 'kiosk',
            ];
            if (!empty($validated['abha_number'])) {
                $patientData['abha_number'] = $validated['abha_number'];
            }
            $patient = Patient::create($patientData);
        }

        // Find doctor: by direct selection, department, or complaint mapping
        $doctor = null;

        if (!empty($validated['doctor_id'])) {
            $doctor = Staff::where('id', $validated['doctor_id'])
                ->where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->first();
        }

        if (!$doctor && !empty($validated['department'])) {
            $doctor = Staff::where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
                ->where('department', $validated['department'])
                ->inRandomOrder()
                ->first();
        }

        if (!$doctor) {
            $mapper = new SpecialtyMapper();
            $specialty = $mapper->suggest(
                $validated['complaint'],
                isset($validated['age']) ? (int) $validated['age'] : null,
                $validated['gender'] ?? null
            );

            $doctor = Staff::where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
                ->where(function ($q) use ($specialty) {
                    $q->where('department', 'like', '%' . str_replace('_', ' ', $specialty) . '%')
                      ->orWhere('specialization', 'like', '%' . str_replace('_', ' ', $specialty) . '%');
                })
                ->first();
        }

        // Fallback to any available doctor
        if (!$doctor) {
            $doctor = Staff::where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
                ->first();
        }

        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'No doctors available at this time.'], 422);
        }

        // Create encounter
        $isEmergency = $validated['is_emergency'] ?? false;
        $encounterNumber = 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        $encounter = Encounter::create([
            'id'                    => Str::uuid()->toString(),
            'hospital_id'          => $hospital->id,
            'patient_id'           => $patient->id,
            'doctor_id'            => $doctor->id,
            'encounter_number'     => $encounterNumber,
            'type'                 => $isEmergency ? 'emergency' : 'consultation',
            'status'               => 'checked_in',
            'channel'              => 'kiosk',
            'triage_classification' => $isEmergency ? 'emergency' : null,
            'triage_score'         => $isEmergency ? 0.95 : null,
            'intake_data'          => [
                'chief_complaint' => $validated['complaint'],
                'who'             => 'self',
                'source'          => 'kiosk_registration',
                'is_emergency'    => $isEmergency,
            ],
        ]);

        // Create appointment
        $slotStart = now()->addMinutes(15); // next available roughly

        // Generate a token unique to this doctor for the day
        $token = Appointment::generateToken($doctor->id, $doctor->department, $slotStart);

        $appointment = Appointment::create([
            'id'                       => Str::uuid()->toString(),
            'hospital_id'             => $hospital->id,
            'encounter_id'            => $encounter->id,
            'patient_id'              => $patient->id,
            'doctor_id'               => $doctor->id,
            'slot_start'              => $slotStart,
            'slot_end'                => $slotStart->copy()->addMinutes($doctor->consultation_duration_default ?? 15),
            'predicted_duration_minutes' => $doctor->consultation_duration_default ?? 15,
            'status'                  => 'checked_in',
            'check_in_time'           => now(),
            'booking_source'          => 'kiosk',
            'notes'                   => $token,
        ]);

        // Calculate position
        $position = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('slot_start', today())
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->where('id', '!=', $appointment->id)
            ->count() + 1;

        $waitMinutes = ($position - 1) * ($doctor->consultation_duration_default ?? 15);

        return response()->json([
            'success'    => true,
            'name'       => $patient->name,
            'token'      => $token,
            'doctor'     => $doctor->name,
            'department' => $doctor->department ?? 'General Medicine',
            'position'   => $position,
            'wait'       => $waitMinutes . ' minutes',
        ]);
    }

    /**
     * Kiosk lab-test booking page: pick tests + a lab slot, no doctor.
     */
    public function labBooking(Request $request)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            return redirect()->route('kiosk.index');
        }

        $tests = \DB::table('available_tests')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('hospital_id')->orWhere('hospital_id', $hospital->id))
            ->orderBy('type')->orderBy('name')
            ->get(['name', 'type', 'price']);

        $slots = \App\Modules\Core\Services\LabSlotService::availableSlots($hospital->id, 7, 12);

        return view('kiosk.lab', compact('hospital', 'tests', 'slots'));
    }

    public function processLabBooking(Request $request)
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            return response()->json(['success' => false, 'message' => 'Hospital not selected.'], 400);
        }

        $v = $request->validate([
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|min:4|max:15',
            'tests'         => 'required|array|min:1',
            'tests.*.name'  => 'required|string|max:255',
            'tests.*.price' => 'nullable|numeric|min:0',
            'tests.*.type'  => 'nullable|string',
            'scheduled_for' => 'nullable|date',
        ]);

        $phone = preg_replace('/[^0-9+]/', '', $v['phone']);
        if (! str_starts_with($phone, '+')) {
            $phone = strlen($phone) === 10 ? '+91' . $phone : '+' . $phone;
        }

        // Find / restore / create the patient (soft-delete aware, like check-in).
        $patient = Patient::withTrashed()->where('hospital_id', $hospital->id)->where('phone', $phone)->first();
        if ($patient && method_exists($patient, 'trashed') && $patient->trashed()) {
            $patient->restore();
        }
        if (! $patient) {
            $patient = Patient::create([
                'id'                  => Str::uuid()->toString(),
                'hospital_id'         => $hospital->id,
                'name'                => $v['name'],
                'phone'               => $phone,
                'phone_verified'      => false,
                'language_preference' => 'en',
                'created_via'         => 'kiosk_lab',
            ]);
        }

        $token = 'LAB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        $scheduledFor = $v['scheduled_for'] ?? null;

        $byType = collect($v['tests'])->groupBy(
            fn ($t) => in_array(($t['type'] ?? 'lab'), ['lab', 'imaging', 'procedure'], true) ? $t['type'] : 'lab'
        );
        foreach ($byType as $type => $items) {
            \App\Modules\Core\Models\Order::create([
                'id'             => Str::uuid()->toString(),
                'hospital_id'    => $hospital->id,
                'patient_id'     => $patient->id,
                'encounter_id'   => null,
                'ordered_by'     => null,
                'type'           => $type,
                'status'         => 'ordered',
                'priority'       => 'routine',
                'items'          => $items->map(fn ($t) => ['name' => $t['name'], 'price' => (float) ($t['price'] ?? 0)])->values()->all(),
                'scheduled_for'  => $scheduledFor,
                'booking_source' => 'kiosk_lab',
                'notes'          => $token,
            ]);
        }

        $total = collect($v['tests'])->sum('price');

        return response()->json([
            'success' => true,
            'token'   => $token,
            'name'    => $patient->name,
            'total'   => $total,
            'when'    => $scheduledFor ? \Carbon\Carbon::parse($scheduledFor)->format('D, M d \a\t g:i A') : 'Walk-in today',
        ]);
    }

    public function patientQueueView(string $doctorId)
    {
        $hospital = $this->resolveHospital();
        $hospitalId = $hospital?->id;

        $doctor = Staff::when($hospitalId, fn($q) => $q->where('hospital_id', $hospitalId))->find($doctorId);
        if (!$doctor) {
            $doctor = Staff::whereRaw("LOWER(name) LIKE ?", ['%' . strtolower($doctorId) . '%'])
                ->when($hospitalId, fn($q) => $q->where('hospital_id', $hospitalId))
                ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])->first();
        }
        if (!$doctor) abort(404, 'Doctor not found');

        $appointments = Appointment::where('doctor_id', $doctor->id)
            ->where('hospital_id', $doctor->hospital_id)
            ->whereDate('slot_start', today())
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->orderBy('slot_start')
            ->with('patient')
            ->get()
            ->map(function ($apt, $idx) {
                $status = is_object($apt->status) ? $apt->status->value : ($apt->status ?? '');
                return [
                    'position' => $idx + 1,
                    'token'    => $apt->notes ?? '',
                    'name'     => $apt->patient?->name ?? 'Patient',
                    'status'   => $status === 'in_progress' ? 'In Consultation' : 'Waiting',
                    'active'   => $status === 'in_progress',
                ];
            });

        return view('kiosk.queue-live', [
            'doctor'       => $doctor,
            'appointments' => $appointments,
        ]);
    }

    public function roomDisplay(Request $request, string $doctorId)
    {
        // Reserved keywords render the lab / imaging board instead of a doctor.
        // e.g. /kiosk/room/lab  ·  /kiosk/room/imaging
        if (in_array(strtolower($doctorId), ['lab', 'laboratory', 'imaging', 'radiology'], true)) {
            return $this->labDisplay($request, strtolower($doctorId));
        }

        $doctor = $this->resolveRoomDoctor($request, $doctorId);

        return view('kiosk.room-display', [
            'doctor'       => $doctor,
            'appointments' => $this->roomAppointments($doctor),
        ]);
    }

    /**
     * The room display's live queue as JSON. The board polls this every 10s
     * instead of re-fetching its own HTML and regex-scraping the data out of it.
     */
    public function roomDisplayJson(Request $request, string $doctorId)
    {
        $doctor = $this->resolveRoomDoctor($request, $doctorId);

        return response()->json([
            'patients'   => $this->roomAppointments($doctor)->values(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /** Resolve a room-display doctor by UUID or name; ?hospital=<slug> scopes it. */
    private function resolveRoomDoctor(?Request $request, string $doctorId): Staff
    {
        $hospitalId = $this->resolveHospital($request)?->id;

        $doctor = Staff::when($hospitalId, fn ($q) => $q->where('hospital_id', $hospitalId))->find($doctorId);
        if (! $doctor) {
            $doctor = Staff::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($doctorId) . '%'])
                ->when($hospitalId, fn ($q) => $q->where('hospital_id', $hospitalId))
                ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
                ->first();
        }
        if (! $doctor) {
            abort(404, 'Doctor not found');
        }

        return $doctor;
    }

    /** Today's checked-in / in-progress / completed queue for one doctor. */
    private function roomAppointments(Staff $doctor)
    {
        return Appointment::where('doctor_id', $doctor->id)
            ->where('hospital_id', $doctor->hospital_id)
            ->whereDate('slot_start', today())
            ->whereIn('status', ['checked_in', 'in_progress', 'completed'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'checked_in' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END, slot_start")
            ->with(['patient', 'encounter'])
            ->get()
            ->map(function ($apt) {
                $status = is_object($apt->status) ? $apt->status->value : ($apt->status ?? '');
                $encounter = $apt->encounter ?? Encounter::where('patient_id', $apt->patient_id)
                    ->where('doctor_id', $apt->doctor_id)
                    ->whereDate('created_at', today())->first();
                $intake = is_array($encounter?->intake_data) ? $encounter->intake_data : [];
                $triage = is_object($encounter?->triage_classification) ? $encounter->triage_classification->value : ($encounter?->triage_classification ?? '');

                return [
                    'token'   => $apt->notes ?? '',
                    'name'    => $apt->patient?->name ?? 'Patient',
                    'status'  => $status,
                    'urgency' => $triage,
                    'isReferral' => ($intake['source'] ?? '') === 'referral',
                ];
            });
    }

    /**
     * Big-screen board for the lab / imaging queue — the counterpart to a
     * doctor's room display. Reached via /kiosk/room/lab (or /imaging).
     * Scope to a hospital with ?hospital=<slug> when several are active.
     */
    private function labDisplay(?Request $request, string $kind = 'lab')
    {
        $hospital = $this->resolveHospital($request);
        if (! $hospital) {
            abort(404, 'Add ?hospital=<slug> to the URL to pick a hospital.');
        }

        $type  = in_array($kind, ['imaging', 'radiology'], true) ? 'imaging' : 'lab';
        $label = $type === 'imaging' ? 'Imaging / Radiology' : 'Laboratory';

        $orders = \App\Modules\Core\Models\Order::where('hospital_id', $hospital->id)
            ->where('type', $type)
            ->where(function ($q) {
                $q->whereIn('status', ['ordered', 'accepted', 'in_progress'])
                  ->orWhere(fn ($q2) => $q2->where('status', 'completed')->whereDate('updated_at', today()));
            })
            ->with('patient')
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'accepted' THEN 1 WHEN 'ordered' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get()
            ->map(function ($o) {
                $status = is_object($o->status) ? $o->status->value : ($o->status ?? 'ordered');
                // Collapse the lab statuses into the display's vocabulary so the
                // same board JS (now-serving / waiting / done) can drive it.
                $bucket = match ($status) {
                    'in_progress'          => 'in_progress',
                    'completed'            => 'completed',
                    default                => 'checked_in', // ordered / accepted = waiting
                };
                $items = collect($o->items ?? [])->pluck('name')->filter()->implode(', ');
                $pr = $o->priority ?? 'routine';

                return [
                    'token'      => $o->notes ?: '',
                    'name'       => $o->patient?->name ?? 'Patient',
                    'status'     => $bucket,
                    'tests'      => $items ?: 'Tests',
                    'urgency'    => $pr === 'stat' ? 'emergency' : ($pr === 'urgent' ? 'urgent' : ''),
                    'isReferral' => false,
                ];
            })
            ->values();

        return view('kiosk.lab-display', [
            'hospital' => $hospital,
            'label'    => $label,
            'kind'     => $type,
            'orders'   => $orders,
        ]);
    }

    public function queueDisplay()
    {
        return view('kiosk.queue-display', ['doctors' => $this->buildQueueDoctors()]);
    }

    /** Live JSON payload for the queue-display board's auto-refresh. */
    public function queueDisplayJson()
    {
        return response()->json(['doctors' => $this->buildQueueDoctors()]);
    }

    /** Today's active per-doctor queues used by both the board view and its JSON refresh. */
    private function buildQueueDoctors()
    {
        return Staff::where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->get()
            ->map(function ($doctor) {
                $appointments = Appointment::where('doctor_id', $doctor->id)
                    ->whereDate('slot_start', today())
                    ->whereIn('status', ['checked_in', 'in_progress'])
                    ->orderBy('slot_start')
                    ->with('patient')
                    ->get();

                $current = $appointments->first(fn ($a) =>
                    (is_object($a->status) ? $a->status->value : $a->status) === 'in_progress'
                );
                $waiting = $appointments->filter(fn ($a) =>
                    (is_object($a->status) ? $a->status->value : $a->status) === 'checked_in'
                )->values();

                return [
                    'id'         => $doctor->id,
                    'name'       => $doctor->name,
                    'department' => $doctor->department ?? 'General',
                    'current'    => $current ? [
                        'token' => $current->notes ?? 'N/A',
                        'name'  => $current->patient?->name ?? 'Patient',
                    ] : null,
                    'waiting' => $waiting->map(fn ($a) => [
                        'token' => $a->notes ?? 'N/A',
                        'name'  => $a->patient?->name ?? 'Patient',
                    ])->toArray(),
                ];
            })
            ->filter(fn ($d) => $d['current'] || count($d['waiting']) > 0)
            ->values();
    }
}

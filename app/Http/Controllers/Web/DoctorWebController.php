<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Billing\Models\Bill;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorWebController extends Controller
{
    private function staff()
    {
        return Auth::user()->staff;
    }

    private function doctorId()
    {
        return $this->staff()?->id;
    }

    public function dashboard()
    {
        $user = Auth::user();
        $staff = $user->staff;
        $doctorName = $staff?->name ?? $user->name ?? 'Doctor';

        $queue = collect();
        if ($staff) {
            $appointments = Appointment::where('doctor_id', $staff->id)
                ->where('hospital_id', $user->hospital_id)
                ->whereDate('slot_start', today())
                ->whereIn('status', ['checked_in', 'in_progress', 'confirmed', 'scheduled', 'completed'])
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'checked_in' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'scheduled' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END, slot_start")
                ->with(['patient'])
                ->get();

            $position = 0;
            $queue = $appointments->map(function ($apt) use (&$position) {
                $position++;
                $patient = $apt->patient;
                $encounter = Encounter::where('patient_id', $apt->patient_id)
                    ->where('doctor_id', $apt->doctor_id)
                    ->whereDate('created_at', today())
                    ->first();

                $intake = is_array($encounter?->intake_data) ? $encounter->intake_data : [];
                $aptStatus = is_object($apt->status) ? $apt->status->value : ($apt->status ?? 'scheduled');
                $triageClass = $encounter?->triage_classification;
                $triageStr = is_object($triageClass) ? $triageClass->value : ($triageClass ?? 'routine');
                $age = $patient?->date_of_birth ? Carbon::parse($patient->date_of_birth)->age : ($patient?->age_approximate ?? '');

                return [
                    'id'            => $apt->id,
                    'encounter_id'  => $encounter?->id,
                    'token'         => $apt->notes ?? ('T-' . str_pad($position, 3, '0', STR_PAD_LEFT)),
                    'name'          => $patient?->name ?? 'Unknown',
                    'complaint'     => $intake['chief_complaint'] ?? 'Not recorded',
                    'urgency'       => $triageStr,
                    'waitTime'      => $apt->check_in_time ? now()->diffInMinutes($apt->check_in_time) . ' min' : $apt->slot_start?->format('h:i A'),
                    'status'        => match($aptStatus) {
                        'completed' => 'done',
                        'in_progress' => 'called',
                        default => 'waiting',
                    },
                    'age'           => $age ?: 0,
                    'gender'        => ucfirst($patient?->gender ?? ''),
                    'phone'         => $patient?->phone ?? '',
                    'language'      => $patient?->language_preference ?? 'en',
                    'intakeSummary' => $intake['chief_complaint'] ?? '',
                    'isReferral'    => ($intake['source'] ?? '') === 'referral',
                    'referralFrom'  => $intake['referral_from'] ?? null,
                    'referralDept'  => $intake['referral_dept'] ?? null,
                    'referralReason' => $intake['referral_reason'] ?? null,
                    'referralUrgency' => $intake['referral_urgency'] ?? null,
                    'previousDiagnosis' => $intake['previous_diagnosis'] ?? [],
                    'previousNotes' => $intake['previous_notes'] ?? null,
                    'allergies'     => is_array($patient?->allergies) ? $patient->allergies : [],
                    'medications'   => is_array($patient?->current_medications) ? $patient->current_medications : [],
                    'insuranceActive' => !empty($patient?->insurance_details),
                    'insuranceProvider' => 'Insured',
                    'abha' => $patient?->abha_number ? $this->formatAbha($patient->abha_number) : null,
                    'previousEncounters' => $patient ? $patient->encounters()
                        ->where('id', '!=', $encounter?->id)
                        ->latest()->limit(5)->get()
                        ->map(fn ($e) => [
                            'id' => $e->id, 'date' => $e->created_at?->format('M d, Y') ?? '',
                            'doctor' => $e->doctor?->name ?? '',
                            'complaint' => is_array($e->intake_data) ? ($e->intake_data['chief_complaint'] ?? '') : '',
                        ])->toArray() : [],
                ];
            });
        }

        $otherDoctors = \App\Modules\Core\Models\Staff::where('hospital_id', $user->hospital_id)
            ->where('is_active', true)->whereIn('role', ['doctor', 'hospital_admin'])
            ->where('id', '!=', $staff?->id)->orderBy('department')
            ->get(['id', 'name', 'department'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'department' => $d->department])
            ->values()->toArray();

        return view('doctor.dashboard', [
            'doctorName'   => $doctorName,
            'queue'        => $queue->values()->toArray(),
            'otherDoctors' => $otherDoctors,
        ]);
    }

    public function stats(Request $request)
    {
        $doctorId = $this->doctorId();
        $staff = $this->staff();
        $period = $request->get('period', 'today');

        // Date range based on period
        switch ($period) {
            case 'week':
                $from = now()->subDays(7)->startOfDay();
                $to = now()->endOfDay();
                $label = 'Last 7 Days';
                break;
            case 'month':
                $from = now()->subDays(30)->startOfDay();
                $to = now()->endOfDay();
                $label = 'Last 30 Days';
                break;
            case 'all':
                $from = Carbon::parse('2020-01-01');
                $to = now()->endOfDay();
                $label = 'All Time';
                break;
            default: // today
                $from = today()->startOfDay();
                $to = today()->endOfDay();
                $label = 'Today';
                $period = 'today';
        }

        // Appointment stats for the period
        $aptsQuery = Appointment::where('doctor_id', $doctorId)
            ->whereBetween('slot_start', [$from, $to]);

        $patients = (clone $aptsQuery)->distinct('patient_id')->count('patient_id');
        $completed = (clone $aptsQuery)->where('status', 'completed')->count();
        $pending = (clone $aptsQuery)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])->count();
        $noShows = (clone $aptsQuery)->where('status', 'no_show')->count();
        $totalApts = (clone $aptsQuery)->count();

        // Encounter stats
        $encQuery = Encounter::where('doctor_id', $doctorId)
            ->whereBetween('created_at', [$from, $to]);

        $totalEncounters = (clone $encQuery)->count();
        $uniquePatients = (clone $encQuery)->distinct('patient_id')->count('patient_id');

        // Revenue
        $revenue = Bill::whereHas('encounter', fn ($q) => $q->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');

        // Average consultation duration (completed only)
        $avgDuration = 0;
        $completedApts = Appointment::where('doctor_id', $doctorId)
            ->whereBetween('slot_start', [$from, $to])
            ->where('status', 'completed')
            ->whereNotNull('consultation_start_time')
            ->whereNotNull('consultation_end_time')
            ->get(['consultation_start_time', 'consultation_end_time']);
        if ($completedApts->count() > 0) {
            $avgDuration = round($completedApts->avg(fn ($a) =>
                abs(Carbon::parse($a->consultation_end_time)->diffInMinutes(Carbon::parse($a->consultation_start_time)))
            ));
        }

        // Recent encounters for this period
        $recentEncounters = Encounter::where('doctor_id', $doctorId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['patient'])->latest()->limit(20)->get()
            ->map(function ($e) {
                $intake = is_array($e->intake_data) ? $e->intake_data : [];
                $status = is_object($e->status) ? $e->status->value : ($e->status ?? '');
                $type = is_object($e->type) ? $e->type->value : ($e->type ?? '');
                return [
                    'id' => $e->id, 'date' => $e->created_at?->format('M d, Y h:i A'),
                    'patient' => $e->patient?->name ?? 'Unknown',
                    'patient_id' => $e->patient_id,
                    'complaint' => $intake['chief_complaint'] ?? '-',
                    'type' => $type, 'status' => $status,
                ];
            });

        return view('doctor.stats', compact(
            'staff', 'period', 'label', 'patients', 'completed', 'pending', 'noShows',
            'totalApts', 'totalEncounters', 'uniquePatients', 'revenue', 'avgDuration', 'recentEncounters'
        ));
    }

    public function myPatients(Request $request)
    {
        $doctorId = $this->doctorId();

        $patientIds = Encounter::where('doctor_id', $doctorId)->distinct()->pluck('patient_id');

        $query = Patient::whereIn('id', $patientIds);
        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }

        $patients = $query->latest()->paginate(20);

        return view('doctor.my-patients', compact('patients'));
    }

    public function patientDetail(string $id)
    {
        $doctorId = $this->doctorId();
        $patient = Patient::findOrFail($id);

        $encounters = Encounter::where('doctor_id', $doctorId)->where('patient_id', $id)
            ->latest()->get()->map(function ($e) {
                $intake = is_array($e->intake_data) ? $e->intake_data : [];
                $status = is_object($e->status) ? $e->status->value : ($e->status ?? '');
                $type = is_object($e->type) ? $e->type->value : ($e->type ?? '');
                return [
                    'id' => $e->id, 'date' => $e->created_at?->format('M d, Y h:i A'),
                    'complaint' => $intake['chief_complaint'] ?? '-', 'type' => $type,
                    'status' => $status, 'encounter_number' => $e->encounter_number,
                ];
            });

        return view('doctor.patient-detail', compact('patient', 'encounters'));
    }

    public function myAppointments(Request $request)
    {
        $doctorId = $this->doctorId();
        $date = $request->get('date', today()->toDateString());

        $appointments = Appointment::where('doctor_id', $doctorId)
            ->whereDate('slot_start', $date)
            ->with(['patient'])
            ->orderBy('slot_start')
            ->get()
            ->map(function ($apt) {
                $status = is_object($apt->status) ? $apt->status->value : ($apt->status ?? 'scheduled');
                return [
                    'id' => $apt->id, 'time' => $apt->slot_start?->format('h:i A'),
                    'patient' => $apt->patient?->name ?? 'Unknown', 'phone' => $apt->patient?->phone ?? '',
                    'token' => $apt->notes ?? '-', 'status' => $status,
                ];
            });

        return view('doctor.my-appointments', compact('appointments', 'date'));
    }

    public function queueJson()
    {
        $user = Auth::user();
        $staff = $user->staff;
        if (!$staff) return response()->json([]);

        $appointments = Appointment::where('doctor_id', $staff->id)
            ->where('hospital_id', $user->hospital_id)
            ->whereDate('slot_start', today())
            ->whereIn('status', ['checked_in', 'in_progress', 'confirmed', 'scheduled', 'completed'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'checked_in' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'scheduled' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END, slot_start")
            ->with(['patient'])
            ->get();

        $position = 0;
        $queue = $appointments->map(function ($apt) use (&$position, $staff) {
            $position++;
            $patient = $apt->patient;
            $encounter = Encounter::where('patient_id', $apt->patient_id)
                ->where('doctor_id', $apt->doctor_id)
                ->whereDate('created_at', today())->first();

            $intake = is_array($encounter?->intake_data) ? $encounter->intake_data : [];
            $aptStatus = is_object($apt->status) ? $apt->status->value : ($apt->status ?? 'scheduled');
            $triageClass = $encounter?->triage_classification;
            $triageStr = is_object($triageClass) ? $triageClass->value : ($triageClass ?? 'routine');
            $age = $patient?->date_of_birth ? Carbon::parse($patient->date_of_birth)->age : ($patient?->age_approximate ?? '');

            return [
                'id'            => $apt->id,
                'encounter_id'  => $encounter?->id,
                'token'         => $apt->notes ?? ('T-' . str_pad($position, 3, '0', STR_PAD_LEFT)),
                'name'          => $patient?->name ?? 'Unknown',
                'complaint'     => $intake['chief_complaint'] ?? 'Not recorded',
                'urgency'       => $triageStr,
                'waitTime'      => $apt->check_in_time ? now()->diffInMinutes($apt->check_in_time) . ' min' : $apt->slot_start?->format('h:i A'),
                'status'        => match($aptStatus) { 'completed' => 'done', 'in_progress' => 'called', default => 'waiting' },
                'age'           => $age ?: 0,
                'gender'        => ucfirst($patient?->gender ?? ''),
                'phone'         => $patient?->phone ?? '',
                'language'      => $patient?->language_preference ?? 'en',
                'intakeSummary' => $intake['chief_complaint'] ?? '',
                'isReferral'    => ($intake['source'] ?? '') === 'referral',
                'referralFrom'  => $intake['referral_from'] ?? null,
                'referralDept'  => $intake['referral_dept'] ?? null,
                'referralReason' => $intake['referral_reason'] ?? null,
                'referralUrgency' => $intake['referral_urgency'] ?? null,
                'previousDiagnosis' => $intake['previous_diagnosis'] ?? [],
                'previousNotes' => $intake['previous_notes'] ?? null,
                'allergies'     => is_array($patient?->allergies) ? $patient->allergies : [],
                'medications'   => is_array($patient?->current_medications) ? $patient->current_medications : [],
                'insuranceActive' => !empty($patient?->insurance_details),
                'insuranceProvider' => 'Insured',
                'abha' => $patient?->abha_number ? $this->formatAbha($patient->abha_number) : null,
                'previousEncounters' => [],
            ];
        });

        return response()->json($queue->values());
    }

    public function callNextPatient(Request $request, string $appointmentId)
    {
        $apt = Appointment::where('doctor_id', $this->doctorId())->findOrFail($appointmentId);
        $apt->update([
            'status' => 'in_progress',
            'consultation_start_time' => now(),
        ]);

        // Also update encounter
        $encounter = Encounter::where('patient_id', $apt->patient_id)
            ->where('doctor_id', $apt->doctor_id)
            ->whereDate('created_at', today())
            ->first();
        if ($encounter) {
            $encounter->update(['status' => 'in_progress']);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Refer a patient to the lab: create lab/imaging/procedure orders (one per type)
     * for the selected tests, attributed to this doctor. Appears on the Lab dashboard.
     */
    public function referToLab(Request $request, string $appointmentId)
    {
        $apt = Appointment::where('doctor_id', $this->doctorId())->findOrFail($appointmentId);

        $v = $request->validate([
            'tests'         => 'required|array|min:1',
            'tests.*.name'  => 'required|string|max:255',
            'tests.*.price' => 'nullable|numeric|min:0',
            'tests.*.type'  => 'nullable|string',
        ]);

        $encounter = Encounter::where('patient_id', $apt->patient_id)
            ->where('doctor_id', $apt->doctor_id)
            ->whereDate('created_at', today())
            ->first();

        $grouped = collect($v['tests'])->groupBy(
            fn ($t) => in_array(($t['type'] ?? 'lab'), ['lab', 'imaging', 'procedure'], true) ? $t['type'] : 'lab'
        );

        $count = 0;
        foreach ($grouped as $type => $items) {
            \App\Modules\Core\Models\Order::create([
                'id'             => \Illuminate\Support\Str::uuid()->toString(),
                'hospital_id'    => Auth::user()->hospital_id,
                'patient_id'     => $apt->patient_id,
                'encounter_id'   => $encounter?->id,
                'ordered_by'     => $this->doctorId(),
                'type'           => $type,
                'status'         => 'ordered',
                'priority'       => 'routine',
                'items'          => $items->map(fn ($t) => ['name' => $t['name'], 'price' => (float) ($t['price'] ?? 0)])->values()->all(),
                'booking_source' => 'doctor_referral',
            ]);
            $count += $items->count();
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Mark a patient as a no-show — removes them from today's active queue and
     * the room display (no_show is excluded from both).
     */
    public function markNoShow(Request $request, string $appointmentId)
    {
        $apt = Appointment::where('doctor_id', $this->doctorId())->findOrFail($appointmentId);
        $apt->update(['status' => 'no_show']);

        return response()->json(['success' => true]);
    }

    /**
     * Skip a patient who is running late: return them to the waiting queue and
     * push them to the back (so Call Next picks others first), to be called later.
     */
    public function skipPatient(Request $request, string $appointmentId)
    {
        $apt = Appointment::where('doctor_id', $this->doctorId())->findOrFail($appointmentId);
        $apt->update([
            'status'                  => 'checked_in',
            'slot_start'              => now(),   // sort to the back of today's queue
            'consultation_start_time' => null,
        ]);

        $encounter = Encounter::where('patient_id', $apt->patient_id)
            ->where('doctor_id', $apt->doctor_id)
            ->whereDate('created_at', today())
            ->first();
        if ($encounter) {
            $encounter->update(['status' => 'checked_in']);
        }

        return response()->json(['success' => true]);
    }

    public function completeConsultation(Request $request, string $appointmentId)
    {
        $apt = Appointment::where('doctor_id', $this->doctorId())->findOrFail($appointmentId);
        $user = Auth::user();

        // Update appointment
        $apt->update([
            'status' => 'completed',
            'consultation_end_time' => now(),
            'actual_duration_minutes' => $apt->consultation_start_time
                ? now()->diffInMinutes($apt->consultation_start_time)
                : null,
        ]);

        // Update encounter
        $encounter = Encounter::where('patient_id', $apt->patient_id)
            ->where('doctor_id', $apt->doctor_id)
            ->whereDate('created_at', today())
            ->first();

        if ($encounter) {
            $updates = ['status' => 'completed'];
            if ($request->has('diagnosis_codes')) $updates['diagnosis_codes'] = $request->input('diagnosis_codes');
            if ($request->has('soap_notes')) $updates['soap_notes'] = $request->input('soap_notes');
            if ($request->has('follow_up_date')) $updates['follow_up_date'] = $request->input('follow_up_date');
            if ($request->filled('vitals')) $updates['vitals'] = array_filter((array) $request->input('vitals'), fn ($x) => $x !== null && $x !== '');
            if ($request->has('advice')) $updates['patient_advice'] = array_values((array) $request->input('advice'));
            $encounter->update($updates);

            // Link ABHA if patient has one
            $patient = Patient::find($apt->patient_id);
            if ($patient?->abha_number) {
                $encounter->update([
                    'abha_number' => $patient->abha_number,
                    'abha_linked' => true,
                ]);
            }
        }

        // Save referral if present
        if ($request->has('referral') && $request->input('referral.doctor_id')) {
            $ref = $request->input('referral');
            \DB::table('referrals')->insert([
                'id'             => \Str::uuid()->toString(),
                'hospital_id'   => $user->hospital_id,
                'patient_id'    => $apt->patient_id,
                'from_doctor_id' => $this->doctorId(),
                'to_doctor_id'  => $ref['doctor_id'],
                'encounter_id'  => $encounter?->id,
                'urgency'       => $ref['urgency'] ?? 'normal',
                'reason'        => $ref['reason'] ?? null,
                'complaint'     => $ref['complaint'] ?? null,
                'preferred_date' => $ref['date'] ?? null,
                'preferred_time' => $ref['time'] ?? null,
                'status'        => 'pending',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Save prescriptions as pharmacy order
        if ($request->has('prescriptions') && count($request->input('prescriptions', [])) > 0) {
            \App\Modules\Core\Models\Order::create([
                'id' => \Str::uuid()->toString(),
                'hospital_id' => $user->hospital_id,
                'encounter_id' => $encounter?->id,
                'patient_id' => $apt->patient_id,
                'ordered_by' => $this->doctorId(),
                'type' => 'pharmacy',
                'status' => 'ordered',
                'items' => $request->input('prescriptions'),
                'priority' => 'routine',
            ]);
        }

        // Save test/imaging orders
        if ($request->has('orders') && count($request->input('orders', [])) > 0) {
            $grouped = collect($request->input('orders'))->groupBy('type');
            foreach ($grouped as $type => $items) {
                \App\Modules\Core\Models\Order::create([
                    'id' => \Str::uuid()->toString(),
                    'hospital_id' => $user->hospital_id,
                    'encounter_id' => $encounter?->id,
                    'patient_id' => $apt->patient_id,
                    'ordered_by' => $this->doctorId(),
                    'type' => $type,
                    'status' => 'ordered',
                    'items' => $items->values()->toArray(),
                    'priority' => 'routine',
                ]);
            }
        }

        // Notify patient — consultation complete
        $followUp = $request->has('follow_up_date') ? $request->input('follow_up_date') : null;
        \App\Modules\Core\Services\WhatsAppNotifier::consultationComplete($apt, $followUp);

        // Auto-generate the bill now that the consultation and its orders are recorded.
        // Idempotent — safe even if a bill already exists. Non-fatal so a billing
        // hiccup never blocks completing the consultation.
        if ($encounter) {
            try {
                app(\App\Modules\Billing\Services\BillingService::class)->generateBill($encounter);
            } catch (\Throwable $e) {
                \Log::warning('[MedOS] Auto bill generation failed', [
                    'encounter_id' => $encounter->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Consultation completed.']);
    }

    public function referrals()
    {
        $doctorId = $this->doctorId();

        $incoming = \DB::table('referrals')
            ->where('to_doctor_id', $doctorId)
            ->join('patients', 'referrals.patient_id', '=', 'patients.id')
            ->join('staff as from_doc', 'referrals.from_doctor_id', '=', 'from_doc.id')
            ->select(
                'referrals.*',
                'patients.name as patient_name', 'patients.phone as patient_phone',
                'patients.gender as patient_gender', 'patients.age_approximate as patient_age',
                'from_doc.name as from_doctor_name', 'from_doc.department as from_department'
            )
            ->orderByRaw("CASE referrals.status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
            ->orderByDesc('referrals.created_at')
            ->get();

        $outgoing = \DB::table('referrals')
            ->where('from_doctor_id', $doctorId)
            ->join('patients', 'referrals.patient_id', '=', 'patients.id')
            ->join('staff as to_doc', 'referrals.to_doctor_id', '=', 'to_doc.id')
            ->select(
                'referrals.*',
                'patients.name as patient_name',
                'to_doc.name as to_doctor_name', 'to_doc.department as to_department'
            )
            ->orderByDesc('referrals.created_at')
            ->get();

        // Lab/imaging/procedure tests this doctor sent to the lab
        $labReferrals = \App\Modules\Core\Models\Order::where('ordered_by', $doctorId)
            ->whereIn('type', ['lab', 'imaging', 'procedure'])
            ->with('patient')
            ->latest()
            ->limit(50)
            ->get();

        return view('doctor.referrals', compact('incoming', 'outgoing', 'labReferrals'));
    }

    public function acceptReferral(string $id)
    {
        $ref = \DB::table('referrals')->where('id', $id)->where('to_doctor_id', $this->doctorId())->first();
        if (!$ref) abort(404);

        $duration = $this->staff()->consultation_duration_default ?? 15;
        $slotStart = $ref->preferred_date && $ref->preferred_time
            ? Carbon::parse($ref->preferred_date . ' ' . $ref->preferred_time)
            : now()->addMinutes(15);

        // If slot is today and in the past, set to now + 15min
        if ($slotStart->lt(now())) {
            $slotStart = now()->addMinutes(15);
        }

        $deptPrefix = strtoupper(substr(str_replace(' ', '', $this->staff()->department ?? 'GEN'), 0, 3));
        $todayCount = Appointment::where('doctor_id', $this->doctorId())->whereDate('slot_start', $slotStart->toDateString())->count();
        $token = $deptPrefix . '-' . str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);

        // Get referring doctor's encounter for context
        $prevEncounter = $ref->encounter_id ? Encounter::find($ref->encounter_id) : null;
        $prevIntake = is_array($prevEncounter?->intake_data) ? $prevEncounter->intake_data : [];
        $prevSoap = is_array($prevEncounter?->soap_notes) ? $prevEncounter->soap_notes : [];
        $prevDiagnosis = is_array($prevEncounter?->diagnosis_codes) ? $prevEncounter->diagnosis_codes : [];
        $fromDoctor = \DB::table('staff')->where('id', $ref->from_doctor_id)->first();

        // Create encounter with referred data pre-filled
        $encounterNumber = 'ENC-' . now()->format('Ymd') . '-' . strtoupper(\Str::random(4));
        $encounter = Encounter::create([
            'id'                    => \Str::uuid()->toString(),
            'hospital_id'          => $ref->hospital_id,
            'patient_id'           => $ref->patient_id,
            'doctor_id'            => $this->doctorId(),
            'encounter_number'     => $encounterNumber,
            'type'                 => $ref->urgency === 'emergency' ? 'emergency' : 'consultation',
            'status'               => 'checked_in',
            'channel'              => 'referral',
            'triage_classification' => $ref->urgency === 'emergency' ? 'emergency' : ($ref->urgency === 'priority' ? 'urgent' : null),
            'intake_data'          => [
                'chief_complaint'   => $ref->complaint ?? $prevIntake['chief_complaint'] ?? 'Referred patient',
                'referral_from'     => $fromDoctor?->name ?? 'Unknown',
                'referral_dept'     => $fromDoctor?->department ?? '',
                'referral_urgency'  => $ref->urgency,
                'referral_reason'   => $ref->reason,
                'previous_diagnosis' => $prevDiagnosis,
                'previous_notes'    => $prevSoap['subjective'] ?? $prevSoap['assessment'] ?? null,
                'source'            => 'referral',
            ],
        ]);

        // Create appointment as checked_in so it shows in queue immediately
        $isToday = $slotStart->isToday();
        $apt = Appointment::create([
            'id'                        => \Str::uuid()->toString(),
            'hospital_id'              => $ref->hospital_id,
            'encounter_id'             => $encounter->id,
            'patient_id'               => $ref->patient_id,
            'doctor_id'                => $this->doctorId(),
            'status'                   => $isToday ? 'checked_in' : 'scheduled',
            'slot_start'               => $slotStart,
            'slot_end'                 => $slotStart->copy()->addMinutes($duration),
            'predicted_duration_minutes' => $duration,
            'check_in_time'            => $isToday ? now() : null,
            'booking_source'           => 'referral',
            'notes'                    => $token,
        ]);

        \DB::table('referrals')->where('id', $id)->update([
            'status' => 'scheduled',
            'appointment_id' => $apt->id,
            'updated_at' => now(),
        ]);

        // Notify patient about the referral appointment
        \App\Modules\Core\Services\WhatsAppNotifier::appointmentBooked($apt);

        $msg = $isToday
            ? "Accepted! Patient added to your queue as $token"
            : "Scheduled for " . $slotStart->format('M d, g:i A') . " as $token";

        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function declineReferral(Request $request, string $id)
    {
        \DB::table('referrals')->where('id', $id)->where('to_doctor_id', $this->doctorId())
            ->update(['status' => 'declined', 'notes' => $request->input('reason', ''), 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Referral declined.']);
    }

    private function formatAbha(?string $abha): ?string
    {
        if (!$abha || strlen($abha) !== 14) return $abha;
        return substr($abha, 0, 2) . '-' . substr($abha, 2, 4) . '-' . substr($abha, 6, 4) . '-' . substr($abha, 10, 4);
    }

    public function consultationHistory(Request $request)
    {
        $doctorId = $this->doctorId();

        $encounters = Encounter::where('doctor_id', $doctorId)
            ->where('status', 'completed')
            ->with(['patient'])
            ->latest()
            ->paginate(20);

        return view('doctor.history', compact('encounters'));
    }

    // ---------------------------------------------------------------
    // Treatment Packages
    // ---------------------------------------------------------------

    public function packages()
    {
        $staff = $this->staff();
        $packages = $staff
            ? \App\Modules\Core\Models\TreatmentPackage::where('staff_id', $staff->id)
                ->orderByDesc('is_active')->orderBy('name')->get()
            : collect();

        return view('doctor.packages', compact('packages'));
    }

    public function storePackage(Request $request)
    {
        $staff = $this->staff();
        if (! $staff) {
            return redirect()->back()->with('error', 'No staff profile linked to your account.');
        }

        $v = $request->validate([
            'name'        => 'required|string|max:120',
            'price'       => 'required|numeric|min:0|max:9999999',
            'description' => 'nullable|string|max:500',
        ]);

        \App\Modules\Core\Models\TreatmentPackage::create([
            'id'          => \Illuminate\Support\Str::uuid()->toString(),
            'hospital_id' => Auth::user()->hospital_id,
            'staff_id'    => $staff->id,
            'name'        => $v['name'],
            'price'       => $v['price'],
            'description' => $v['description'] ?? null,
            'is_active'   => true,
        ]);

        return redirect()->route('web.doctor.packages')->with('success', 'Package added.');
    }

    public function updatePackage(Request $request, string $id)
    {
        $pkg = \App\Modules\Core\Models\TreatmentPackage::where('staff_id', $this->doctorId())->findOrFail($id);

        $v = $request->validate([
            'name'        => 'required|string|max:120',
            'price'       => 'required|numeric|min:0|max:9999999',
            'description' => 'nullable|string|max:500',
        ]);

        $pkg->update([
            'name'        => $v['name'],
            'price'       => $v['price'],
            'description' => $v['description'] ?? null,
        ]);

        return redirect()->route('web.doctor.packages')->with('success', 'Package updated.');
    }

    public function togglePackage(string $id)
    {
        $pkg = \App\Modules\Core\Models\TreatmentPackage::where('staff_id', $this->doctorId())->findOrFail($id);
        $pkg->update(['is_active' => ! $pkg->is_active]);

        return redirect()->route('web.doctor.packages')->with('success', 'Package updated.');
    }

    public function deletePackage(string $id)
    {
        \App\Modules\Core\Models\TreatmentPackage::where('staff_id', $this->doctorId())->where('id', $id)->delete();

        return redirect()->route('web.doctor.packages')->with('success', 'Package removed.');
    }
}

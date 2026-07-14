<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminWebController extends Controller
{
    public function dashboard()
    {
        $hospitalId = Auth::user()->hospital_id;

        $patientsToday = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', today())
            ->distinct('patient_id')
            ->count('patient_id');

        // SQLite-compatible avg wait calculation
        $avgWaitTime = 0;
        $waitEntries = QueueEntry::where('hospital_id', $hospitalId)
            ->whereDate('created_at', today())
            ->whereNotNull('called_at')
            ->get(['created_at', 'called_at']);

        if ($waitEntries->count() > 0) {
            $totalMinutes = $waitEntries->sum(fn ($e) =>
                $e->called_at->diffInMinutes($e->created_at)
            );
            $avgWaitTime = round($totalMinutes / $waitEntries->count());
        }

        $totalToday = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', today())
            ->count();

        $aiHandled = Encounter::where('hospital_id', $hospitalId)
            ->whereDate('created_at', today())
            ->where('channel', 'whatsapp')
            ->count();

        $aiRate = $totalToday > 0 ? round(($aiHandled / $totalToday) * 100) : 0;

        $revenueToday = Bill::where('hospital_id', $hospitalId)
            ->whereDate('created_at', today())
            ->sum('total_amount') ?? 0;

        // Active queues by doctor
        $queues = Staff::where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->get()
            ->map(function ($doctor) {
                $depth = Appointment::where('doctor_id', $doctor->id)
                    ->whereDate('slot_start', today())
                    ->whereIn('status', ['checked_in', 'in_progress', 'confirmed'])
                    ->count();

                return [
                    'doctor'     => $doctor->name,
                    'department' => $doctor->department ?? 'General',
                    'depth'      => $depth,
                ];
            })->filter(fn ($q) => $q['depth'] > 0)->values();

        $quickStats = [
            'pendingAppointments' => Appointment::where('hospital_id', $hospitalId)
                ->whereDate('slot_start', today())
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->count(),
            'insurancePending' => Bill::where('hospital_id', $hospitalId)
                ->where('insurance_covered', '>', 0)
                ->whereNotIn('payment_status', ['paid', 'waived'])
                ->count(),
            'billsUnpaid' => Bill::where('hospital_id', $hospitalId)
                ->where('payment_status', 'pending')
                ->count(),
            'consultationsDone' => Appointment::where('hospital_id', $hospitalId)
                ->whereDate('slot_start', today())
                ->where('status', 'completed')
                ->count(),
            'noShows' => Appointment::where('hospital_id', $hospitalId)
                ->whereDate('slot_start', today())
                ->where('status', 'no_show')
                ->count(),
        ];

        $recentActivity = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', today())
            ->with(['patient', 'doctor'])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($apt) => [
                'id'      => $apt->id,
                'type'    => match ($apt->getRawOriginal('status')) {
                    'checked_in' => 'check_in',
                    'completed'  => 'completed',
                    'cancelled'  => 'cancelled',
                    default      => 'appointment',
                },
                'message' => ($apt->patient?->name ?? 'Patient') . ' - ' . ucfirst(str_replace('_', ' ', $apt->getRawOriginal('status') ?? 'scheduled')) . ' with ' . ($apt->doctor?->name ?? 'Doctor'),
                'time'    => $apt->updated_at?->diffForHumans() ?? '',
            ]);

        // --- Advanced metrics ---

        // 7-day trend (appointments + revenue per day)
        $weeklyTrend = collect(range(6, 0))->map(function ($daysAgo) use ($hospitalId) {
            $date = today()->subDays($daysAgo);
            return [
                'label'        => $date->format('D'),
                'date'         => $date->format('M j'),
                'isToday'      => $date->isToday(),
                'appointments' => Appointment::where('hospital_id', $hospitalId)
                    ->whereDate('slot_start', $date)->count(),
                'revenue'      => (float) (Bill::where('hospital_id', $hospitalId)
                    ->whereDate('created_at', $date)->sum('total_amount') ?? 0),
            ];
        });
        $maxAppts   = max(1, (int) $weeklyTrend->max('appointments'));
        $maxRevenue = max(1, (float) $weeklyTrend->max('revenue'));

        // Day-over-day deltas
        $yesterday = today()->subDay();
        $patientsYesterday = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', $yesterday)
            ->distinct('patient_id')->count('patient_id');
        $revenueYesterday = (float) (Bill::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $yesterday)->sum('total_amount') ?? 0);

        $deltas = [
            'patients' => $this->pctDelta($patientsToday, $patientsYesterday),
            'revenue'  => $this->pctDelta($revenueToday, $revenueYesterday),
        ];

        // Today's appointment status breakdown
        $todayAppts = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', today())
            ->with('doctor')
            ->get();

        $statusBreakdown = $todayAppts
            ->groupBy(fn ($a) => $a->getRawOriginal('status') ?? 'scheduled')
            ->map->count();

        // Department load (appointments per department today)
        $departmentLoad = $todayAppts
            ->groupBy(fn ($a) => $a->doctor?->department ?: 'General')
            ->map->count()
            ->sortDesc();
        $maxDeptLoad = max(1, (int) ($departmentLoad->max() ?? 0));

        // Headline counters
        $newPatientsToday = Patient::where('hospital_id', $hospitalId)
            ->whereDate('created_at', today())->count();
        $activeDoctors = Staff::where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])->count();
        $totalAppointmentsToday = $totalToday;

        return view('admin.dashboard', compact(
            'patientsToday', 'avgWaitTime', 'aiRate', 'revenueToday',
            'queues', 'recentActivity', 'quickStats',
            'weeklyTrend', 'maxAppts', 'maxRevenue', 'deltas',
            'statusBreakdown', 'departmentLoad', 'maxDeptLoad',
            'newPatientsToday', 'activeDoctors', 'totalAppointmentsToday',
        ));
    }

    /**
     * Percentage change vs a previous value. Returns [value, direction].
     */
    private function pctDelta($current, $previous): array
    {
        if ($previous <= 0) {
            return ['pct' => $current > 0 ? 100 : 0, 'dir' => $current > 0 ? 'up' : 'flat'];
        }
        $pct = round((($current - $previous) / $previous) * 100);
        return ['pct' => abs($pct), 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat')];
    }

    public function patients(Request $request)
    {
        $query = Patient::where('hospital_id', Auth::user()->hospital_id);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $patients = $query->latest()->paginate(20);
        return view('admin.patients', compact('patients'));
    }

    /** Dedicated full registration page (demographics + insurance + ID verification). */
    public function registerForm()
    {
        return view('admin.patient-register');
    }

    /** Validation rules shared by register/add/edit patient forms. */
    private function patientRules(): array
    {
        return [
            'name'                     => 'required|string|max:255',
            'phone'                    => 'required|string|max:20',
            'gender'                   => 'nullable|string|in:male,female,other',
            'email'                    => 'nullable|email|max:255',
            'date_of_birth'            => 'nullable|date',
            'age_approximate'          => 'nullable|integer|min:0|max:130',
            'language_preference'      => 'nullable|string|max:10',
            'blood_group'              => 'nullable|string|max:10',
            'address'                  => 'nullable|string|max:500',
            'city'                     => 'nullable|string|max:120',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:20',
            'ins_provider'             => 'nullable|string|max:255',
            'ins_policy_no'            => 'nullable|string|max:100',
            'ins_tpa'                  => 'nullable|string|max:255',
            'ins_valid_till'           => 'nullable|date',
            'ins_coverage'             => 'nullable|numeric|min:0',
            'health_id'                => 'nullable|string|max:40',
            'health_id_verified'       => 'nullable|boolean',
        ];
    }

    /** Build the Patient attribute array (demographics + insurance_details + health ID) from validated input. */
    private function patientAttributes(array $v): array
    {
        $phone = preg_replace('/\s+/', '', $v['phone']);
        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '+91' . $phone;
        }

        $attrs = [
            'name'                    => $v['name'],
            'phone'                   => $phone,
            'gender'                  => $v['gender'] ?? null,
            'email'                   => $v['email'] ?? null,
            'date_of_birth'           => $v['date_of_birth'] ?? null,
            'age_approximate'         => $v['age_approximate'] ?? null,
            'language_preference'     => $v['language_preference'] ?? 'en',
            'blood_group'             => $v['blood_group'] ?? null,
            'address'                 => $v['address'] ?? null,
            'city'                    => $v['city'] ?? null,
            'emergency_contact_name'  => $v['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $v['emergency_contact_phone'] ?? null,
        ];

        // Insurance -> encrypted array (only when something was entered)
        $insurance = array_filter([
            'provider'   => $v['ins_provider'] ?? null,
            'policy_no'  => $v['ins_policy_no'] ?? null,
            'tpa'        => $v['ins_tpa'] ?? null,
            'valid_till' => $v['ins_valid_till'] ?? null,
            'coverage'   => isset($v['ins_coverage']) && $v['ins_coverage'] !== '' ? (float) $v['ins_coverage'] : null,
        ], fn ($x) => $x !== null && $x !== '');
        $attrs['insurance_details'] = $insurance ?: null;

        // Health ID (ABHA / Emirates ID)
        if (! empty($v['health_id'])) {
            $attrs['abha_number']   = preg_replace('/[\s-]/', '', $v['health_id']);
            $attrs['abha_verified'] = (bool) ($v['health_id_verified'] ?? false);
        }

        return $attrs;
    }

    public function storePatient(Request $request)
    {
        $v = $request->validate($this->patientRules());
        $hospitalId = Auth::user()->hospital_id;
        $attrs = $this->patientAttributes($v);

        if (Patient::where('hospital_id', $hospitalId)->where('phone', $attrs['phone'])->exists()) {
            return redirect()->back()->withInput()->with('error', 'A patient with this phone number already exists.');
        }

        $patient = Patient::create(array_merge([
            'id'          => Str::uuid()->toString(),
            'hospital_id' => $hospitalId,
            'created_via' => 'reception',
        ], $attrs));

        return redirect()->route('web.admin.patients.show', $patient->id)
            ->with('success', 'Patient registered — ' . $patient->name . '.');
    }

    public function updatePatient(Request $request, string $id)
    {
        $patient = Patient::where('hospital_id', Auth::user()->hospital_id)->findOrFail($id);
        $v = $request->validate($this->patientRules());
        $attrs = $this->patientAttributes($v);

        $dupe = Patient::where('hospital_id', $patient->hospital_id)
            ->where('phone', $attrs['phone'])
            ->where('id', '!=', $patient->id)
            ->exists();
        if ($dupe) {
            return redirect()->back()->withInput()->with('error', 'Another patient already uses this phone number.');
        }

        $patient->update($attrs);

        return redirect()->back()->with('success', 'Patient updated successfully.');
    }

    /** Region-aware health-ID verification (India: ABHA via ABHAService; UAE: Emirates ID format). */
    public function verifyHealthId(Request $request)
    {
        $request->validate(['health_id' => 'required|string|max:40']);
        $id = trim($request->input('health_id'));
        $label = \App\Modules\Core\Services\RegionService::healthIdLabel();

        if (\App\Modules\Core\Services\RegionService::isIndia()) {
            // Strip separators so a 14-digit ABHA in any dash/space format validates.
            $res = app(\App\Modules\ABHA\Services\ABHAService::class)->verifyAbha(preg_replace('/[\s-]/', '', $id));
            if (! ($res['valid'] ?? false)) {
                return response()->json(['verified' => false, 'label' => $label, 'message' => 'Invalid ' . $label . ' format.']);
            }
            $p = $res['profile'] ?? [];
            $g = strtolower((string) ($p['gender'] ?? ''));
            return response()->json([
                'verified' => true,
                'label'    => $label,
                'message'  => $label . ' verified.',
                'profile'  => [
                    'name'          => $p['name'] ?? null,
                    'gender'        => $g === 'm' ? 'male' : ($g === 'f' ? 'female' : null),
                    'date_of_birth' => $p['dob'] ?? null,
                    'address'       => $p['address'] ?? null,
                ],
            ]);
        }

        // UAE Emirates ID: 15 digits starting 784
        $digits = preg_replace('/\D/', '', $id);
        $ok = strlen($digits) === 15 && str_starts_with($digits, '784');
        return response()->json([
            'verified' => $ok,
            'label'    => $label,
            'message'  => $ok ? $label . ' format valid.' : $label . ' must be 15 digits starting with 784.',
            'profile'  => [],
        ]);
    }

    public function deletePatient(string $id)
    {
        $patient = Patient::where('hospital_id', Auth::user()->hospital_id)->findOrFail($id);
        $patient->delete();

        return redirect()->route('web.admin.patients')->with('success', 'Patient deleted successfully.');
    }

    public function patientDetail($id)
    {
        $patient = Patient::where('hospital_id', Auth::user()->hospital_id)->findOrFail($id);

        $encounters = $patient->encounters()->with(['doctor'])->latest()->get();
        $appointments = $patient->appointments()->with(['doctor'])->latest()->get();
        $bills = Bill::where('patient_id', $patient->id)->latest()->get();

        $timeline = collect();

        foreach ($appointments as $apt) {
            $timeline->push([
                'type'        => 'appointment',
                'title'       => 'Appointment with ' . ($apt->doctor?->name ?? 'Doctor'),
                'date'        => $apt->slot_start?->format('M d, Y h:i A') ?? '',
                'description' => $apt->notes,
                'status'      => $apt->status ?? 'scheduled',
                'sort_date'   => $apt->slot_start,
            ]);
        }

        foreach ($encounters as $enc) {
            $intake = is_array($enc->intake_data) ? $enc->intake_data : [];
            $encType = is_object($enc->type) ? $enc->type->value : ($enc->type ?? 'visit');
            $encStatus = is_object($enc->status) ? $enc->status->value : ($enc->status ?? 'unknown');
            $timeline->push([
                'type'        => 'encounter',
                'title'       => 'Encounter: ' . ($intake['chief_complaint'] ?? $encType),
                'date'        => $enc->created_at?->format('M d, Y h:i A') ?? '',
                'description' => $enc->encounter_number,
                'status'      => $encStatus,
                'sort_date'   => $enc->created_at,
            ]);
        }

        foreach ($bills as $bill) {
            $payStatus = is_object($bill->payment_status) ? $bill->payment_status->value : ($bill->payment_status ?? 'pending');
            $timeline->push([
                'type'        => 'billing',
                'title'       => 'Bill #' . $bill->bill_number . ' - ₹' . number_format($bill->total_amount ?? 0, 2),
                'date'        => $bill->created_at?->format('M d, Y') ?? '',
                'description' => 'Payment: ' . $payStatus,
                'status'      => $payStatus,
                'sort_date'   => $bill->created_at,
            ]);
        }

        $timeline = $timeline->sortByDesc('sort_date')->values()->toArray();

        return view('admin.patient-detail', compact('patient', 'encounters', 'bills', 'timeline'));
    }

    public function appointments(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $view   = in_array($request->get('view'), ['board', 'upcoming'], true) ? $request->get('view') : 'list';
        $date   = $request->get('date', today()->toDateString());
        $search = trim((string) $request->get('search', ''));

        $query = Appointment::where('hospital_id', $hospitalId)->with(['patient', 'doctor']);

        if ($view === 'upcoming') {
            $query->where('slot_start', '>=', now()->startOfDay())
                ->whereNotIn('status', ['cancelled', 'no_show', 'completed']);
        } else {
            $query->whereDate('slot_start', $date);
        }

        // A logged-in practitioner (doctor / dentist / dietitian) sees only their own
        // appointments; admins and reception see everyone's and can filter by doctor.
        $user = Auth::user();
        $role = is_object($user->role) ? $user->role->value : $user->role;
        $selfStaffId = $user->staff?->id;
        $isPractitioner = in_array($role, ['doctor', 'dentist', 'dietitian'], true) && $selfStaffId;

        // A specialty practitioner can jump from an appointment straight into their
        // module for that patient (dentist → Dental, dietitian → Clinical Nutrition).
        $consultModule = match ($role) {
            'dentist'   => 'dental',
            'dietitian' => 'dietary',
            default     => null,
        };

        if ($isPractitioner) {
            $query->where('doctor_id', $selfStaffId);
        } elseif ($doctorId = $request->get('doctor')) {
            $query->where('doctor_id', $doctorId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $appointments = (clone $query)->orderBy('slot_start')->paginate(30)->withQueryString();

        // Day board: same day's appointments grouped by doctor (built only in board view).
        $board = collect();
        if ($view === 'board') {
            $board = (clone $query)->orderBy('slot_start')->get()
                ->groupBy(fn ($a) => $a->doctor?->name ?? 'Unassigned');
        }

        $doctors = Staff::where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->orderBy('name')
            ->get();

        // Lab bookings (orders) for the same date — direct bookings and referrals.
        // Not relevant to an individual practitioner's own list.
        $labBookings = $isPractitioner ? collect() : \App\Modules\Core\Models\Order::labBookingsForDate($hospitalId, $date);

        $tests = $this->queueTests();

        return view('admin.appointments', compact('appointments', 'doctors', 'labBookings', 'view', 'date', 'search', 'board', 'tests', 'isPractitioner', 'consultModule'));
    }

    public function staff()
    {
        $staff = Staff::where('hospital_id', Auth::user()->hospital_id)
            ->orderBy('name')
            ->get();

        return view('admin.staff', compact('staff'));
    }

    public function settings()
    {
        $hospital = Hospital::find(Auth::user()->hospital_id);
        $enabled = $hospital?->modules_enabled;
        $moduleList = collect(\App\Modules\Core\Support\ModuleCatalog::MODULES)
            ->map(fn ($m, $k) => [
                'key'         => $k,
                'name'        => $m['name'],
                'description' => $m['description'],
                'category'    => $m['category'] ?? 'Other',
                'enabled'     => empty($enabled) || in_array($k, $enabled, true),
            ])->values()->all();

        $cfg = is_array($hospital?->config) ? $hospital->config : json_decode($hospital?->config ?? '{}', true);
        // Departments may be stored either as {name, active} objects (from this
        // settings screen) or as a legacy array of plain strings (older seeds).
        // Normalise to objects so the UI always has a name + active flag.
        $rawDepartments = $cfg['departments'] ?? null;
        if (! is_array($rawDepartments)) {
            $departments = [
                ['name' => 'General Medicine', 'active' => true],
                ['name' => 'Cardiology', 'active' => true],
                ['name' => 'Pediatrics', 'active' => true],
                ['name' => 'Orthopedics', 'active' => true],
            ];
        } else {
            $departments = collect($rawDepartments)
                ->map(fn ($d) => is_array($d)
                    ? ['name' => $d['name'] ?? '', 'active' => (bool) ($d['active'] ?? $d['is_active'] ?? true)]
                    : ['name' => (string) $d, 'active' => true])
                ->filter(fn ($d) => trim($d['name']) !== '')
                ->values()
                ->all();
        }
        $areas = array_merge([
            'waiting' => 'Floor 2, Waiting Area',
            'lab'     => 'Sample Collection Counter',
        ], is_array($cfg['areas'] ?? null) ? $cfg['areas'] : []);
        $gst = [
            'gstin' => $cfg['gstin'] ?? '',
            'state' => $cfg['gst_state'] ?? '',
        ];
        $aiCfg = [
            'provider' => $cfg['ai']['provider'] ?? 'anthropic',
            'model'    => $cfg['ai']['model'] ?? 'claude-sonnet-4-20250514',
            'hasKey'   => ! empty($cfg['ai']['api_key']),
        ];
        $waCfg = [
            'number'   => $cfg['whatsapp']['number'] ?? '',
            'provider' => $cfg['whatsapp']['provider'] ?? 'meta',
            'hasToken' => ! empty($cfg['whatsapp']['token']),
        ];

        $biRaw = $cfg['billing_integration'] ?? [];
        $biCfg = [
            'enabled'    => (bool) ($biRaw['enabled'] ?? false),
            'connector'  => $biRaw['connector'] ?? 'generic_webhook',
            'endpoint'   => $biRaw['endpoint'] ?? '',
            'events'     => $biRaw['events'] ?? ['bill.created', 'bill.paid', 'bill.refunded', 'bill.cancelled'],
            'hasSecret'  => ! empty($biRaw['secret']),
            'hasSigning' => ! empty($biRaw['signing_secret']),
            'connectors' => array_map(
                fn ($c) => ['code' => $c->code(), 'label' => $c->label(), 'fields' => $c->configFields()],
                \App\Modules\Billing\Integrations\BillingConnectorRegistry::all()
            ),
        ];
        $biEvents = [
            'bill.created'   => 'Bill created',
            'bill.paid'      => 'Bill paid',
            'bill.partial'   => 'Partial payment',
            'bill.refunded'  => 'Refunded',
            'bill.cancelled' => 'Cancelled',
        ];

        return view('admin.settings', compact('hospital', 'moduleList', 'departments', 'areas', 'gst', 'aiCfg', 'waCfg', 'biCfg', 'biEvents'));
    }

    /** Persist the hospital's GST identity (GSTIN + state) for tax invoices. */
    public function saveGstDetails(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $v = $request->validate([
            'gstin' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:60',
        ]);

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $config['gstin']     = strtoupper(trim($v['gstin'] ?? ''));
        $config['gst_state'] = trim($v['state'] ?? '');
        $hospital->update(['config' => $config]);

        return redirect()->route('web.admin.settings')->with('success', 'GST details saved.');
    }

    /** Persist the hospital's patient-facing areas (waiting area, lab/collection area). */
    public function saveAreas(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $v = $request->validate([
            'waiting' => 'nullable|string|max:120',
            'lab'     => 'nullable|string|max:120',
        ]);

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $config['areas'] = [
            'waiting' => trim($v['waiting'] ?? '') ?: 'Waiting Area',
            'lab'     => trim($v['lab'] ?? '') ?: 'Sample Collection Counter',
        ];
        $hospital->update(['config' => $config]);

        return redirect()->route('web.admin.settings')->with('success', 'Patient areas saved.');
    }

    /** Persist which modules are enabled for this hospital. */
    public function updateModules(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $valid = \App\Modules\Core\Support\ModuleCatalog::keys();
        $submitted = (array) $request->input('modules', []);

        // Preserve any enabled keys we don't manage here (e.g. scheduling, queue, analytics).
        $existing = (array) ($hospital->modules_enabled ?? []);
        $preserved = array_diff($existing, $valid);
        $enabled = array_values(array_unique(array_merge($preserved, array_intersect($valid, $submitted))));

        // An empty list means "all modules on" (never-configured default). Once a
        // user has explicitly saved, keep a sentinel so disabling everything sticks
        // instead of silently re-enabling all modules.
        if (empty($enabled)) {
            $enabled = ['__configured__'];
        }

        $hospital->update(['modules_enabled' => $enabled]);

        return redirect()->route('web.admin.settings')->with('success', 'Module configuration saved.');
    }

    public function saveSettings(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);

        $v = $request->validate([
            'name'    => 'required|string|max:255',
            'slug'    => 'nullable|string|max:100|unique:hospitals,slug,' . $hospital->id,
            'country' => 'required|in:IN,AE',
            'city'    => 'required|string|max:100',
            'state'   => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
        ]);

        $hospital->update([
            'name'    => $v['name'],
            'slug'    => $v['slug'] ?: $hospital->slug,
            'country' => $v['country'],
            'city'    => $v['city'],
            'state'   => $v['state'] ?? $hospital->state,
            'address' => $v['address'] ?? $hospital->address,
            'phone'   => $v['phone'] ?? $hospital->phone,
            'email'   => $v['email'] ?? $hospital->email,
        ]);

        \App\Modules\Core\Services\RegionService::reset();

        return redirect()->route('web.admin.settings')->with('success', 'Hospital settings saved.');
    }

    public function saveOperatingHours(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);

        $request->validate(['hours' => 'required|array']);

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $config['operating_hours'] = $request->input('hours');
        $hospital->update(['config' => $config]);

        return response()->json(['success' => true, 'message' => 'Operating hours saved.']);
    }

    /** Persist the hospital's department list to config. */
    public function saveDepartments(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $request->validate([
            'departments'          => 'array',
            'departments.*.name'   => 'required|string|max:100',
            'departments.*.active' => 'nullable|boolean',
        ]);

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $config['departments'] = collect($request->input('departments', []))
            ->map(fn ($d) => ['name' => trim($d['name']), 'active' => (bool) ($d['active'] ?? true)])
            ->filter(fn ($d) => $d['name'] !== '')
            ->values()->all();
        $hospital->update(['config' => $config]);

        return response()->json(['success' => true, 'message' => 'Departments saved.']);
    }

    /** Persist AI provider settings (key is kept server-side; blank leaves it unchanged). */
    public function saveAiSettings(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $v = $request->validate([
            'provider' => 'required|string|max:40',
            'model'    => 'required|string|max:80',
            'api_key'  => 'nullable|string|max:255',
        ]);

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $ai = $config['ai'] ?? [];
        $ai['provider'] = $v['provider'];
        $ai['model'] = $v['model'];
        if (! empty($v['api_key'])) {
            $ai['api_key'] = encrypt($v['api_key']);
        }
        $config['ai'] = $ai;
        $hospital->update(['config' => $config]);

        return response()->json(['success' => true, 'message' => 'AI settings saved.']);
    }

    /** Persist WhatsApp integration settings (token kept server-side; blank leaves it unchanged). */
    public function saveWhatsappSettings(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $v = $request->validate([
            'number'   => 'nullable|string|max:30',
            'provider' => 'required|string|max:40',
            'token'    => 'nullable|string|max:255',
        ]);

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $wa = $config['whatsapp'] ?? [];
        $wa['number'] = $v['number'] ?? ($wa['number'] ?? '');
        $wa['provider'] = $v['provider'];
        if (! empty($v['token'])) {
            $wa['token'] = encrypt($v['token']);
        }
        $config['whatsapp'] = $wa;
        $hospital->update(['config' => $config]);

        return response()->json(['success' => true, 'message' => 'WhatsApp settings saved.']);
    }

    /** Persist the hospital's external billing-software integration config. */
    public function saveBillingIntegration(Request $request)
    {
        $hospital = Hospital::findOrFail(Auth::user()->hospital_id);
        $v = $request->validate([
            'enabled'        => 'nullable|boolean',
            'connector'      => 'required|string|max:60',
            'endpoint'       => 'nullable|url|max:500',
            'events'         => 'nullable|array',
            'events.*'       => 'string|max:40',
            'secret'         => 'nullable|string|max:1000',
            'signing_secret' => 'nullable|string|max:1000',
        ]);

        if (! \App\Modules\Billing\Integrations\BillingConnectorRegistry::make($v['connector'])) {
            return response()->json(['success' => false, 'message' => 'Unknown connector.'], 422);
        }

        $config = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);
        $bi = $config['billing_integration'] ?? [];
        $bi['enabled'] = (bool) ($v['enabled'] ?? false);
        $bi['connector'] = $v['connector'];
        $bi['endpoint'] = $v['endpoint'] ?? '';
        $bi['events'] = array_values($v['events'] ?? []);
        // Secrets: encrypt only when a new value is supplied (blank = keep existing).
        if ($request->filled('secret')) {
            $bi['secret'] = encrypt($v['secret']);
        }
        if ($request->filled('signing_secret')) {
            $bi['signing_secret'] = encrypt($v['signing_secret']);
        }
        $config['billing_integration'] = $bi;
        $hospital->update(['config' => $config]);

        return response()->json(['success' => true, 'message' => 'Billing integration saved.']);
    }

    /** Fire a connectivity test against the saved billing-integration config. */
    public function testBillingIntegration(Request $request)
    {
        $hid = Auth::user()->hospital_id;
        $bi = \App\Modules\Billing\Integrations\BillingIntegrationSettings::for($hid);
        if (! $bi || empty($bi['connector'])) {
            return response()->json(['success' => false, 'message' => 'Save the integration first, then test.'], 422);
        }
        $connector = \App\Modules\Billing\Integrations\BillingConnectorRegistry::make($bi['connector']);
        if (! $connector) {
            return response()->json(['success' => false, 'message' => 'Unknown connector.'], 422);
        }

        $result = $connector->test(\App\Modules\Billing\Integrations\BillingIntegrationSettings::credentials($bi));

        return response()->json(['success' => $result['ok'], 'message' => $result['message']]);
    }

    // -----------------------------------------------------------------
    // API keys — let a hospital's own billing software pull from MedOS
    // -----------------------------------------------------------------

    public function apiKeys()
    {
        $tokens = Auth::user()->tokens()->latest()->get()->map(fn ($t) => [
            'id'        => $t->id,
            'name'      => $t->name,
            'abilities' => is_array($t->abilities) ? $t->abilities : [],
            'last_used' => $t->last_used_at ? $t->last_used_at->diffForHumans() : 'never',
            'created'   => $t->created_at->format('M d, Y'),
        ]);

        return view('admin.api-keys', ['tokens' => $tokens, 'plainToken' => session('plain_token')]);
    }

    public function createApiKey(Request $request)
    {
        $v = $request->validate([
            'name'   => 'required|string|max:60',
            'access' => 'required|in:read,full',
        ]);

        $abilities = $v['access'] === 'full'
            ? ['billing:read', 'billing:write', 'patients:read']
            : ['billing:read', 'patients:read'];

        $token = Auth::user()->createToken('api:' . $v['name'], $abilities);

        return redirect()->route('web.admin.api-keys')
            ->with('plain_token', $token->plainTextToken)
            ->with('success', 'API key created — copy it now, it will not be shown again.');
    }

    public function revokeApiKey(string $id)
    {
        Auth::user()->tokens()->where('id', $id)->delete();

        return redirect()->route('web.admin.api-keys')->with('success', 'API key revoked.');
    }

    // -----------------------------------------------------------------
    // Patient Information Desk — front-office automated inquiry
    // -----------------------------------------------------------------

    public function infoDesk()
    {
        $hid = Auth::user()->hospital_id;
        $today = strtolower(now()->format('l'));
        $doctors = \App\Modules\Core\Models\Staff::where('hospital_id', $hid)
            ->where('is_active', true)->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->orderBy('name')->get()->map(function ($d) use ($today) {
                $sched = is_array($d->schedule) ? $d->schedule : (json_decode($d->schedule ?? '{}', true) ?: []);
                $blocks = $sched[$today] ?? [];
                $hours = empty($blocks) ? null : implode(', ', array_map(fn ($b) => ($b['start'] ?? '') . '–' . ($b['end'] ?? ''), $blocks));

                return (object) ['name' => $d->name, 'department' => $d->department, 'specialization' => $d->specialization, 'today' => $hours];
            });
        $departments = $doctors->pluck('department')->filter()->unique()->sort()->values();

        return view('admin.info-desk', compact('doctors', 'departments'));
    }

    /** Smart inquiry: auto-detects a token vs a name/phone search. */
    public function infoDeskLookup(Request $request)
    {
        $hid = Auth::user()->hospital_id;
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['type' => 'none']);
        }

        // Token-like query (PED-001, OP12) → resolve that token directly.
        if (preg_match('/^[A-Za-z]{2,5}-?\d{1,4}$/', $q)) {
            $token = strtoupper($q);
            $appt = \App\Modules\Appointment\Models\Appointment::where('hospital_id', $hid)
                ->where(function ($x) use ($token, $q) {
                    $x->where('notes', $token)->orWhere('notes', $q);
                })
                ->whereDate('slot_start', '>=', today()->subDay())
                ->with(['patient:id,name,phone', 'doctor:id,name,department'])
                ->latest('slot_start')->first();
            if (! $appt) {
                return response()->json(['type' => 'token', 'token' => null, 'query' => $q]);
            }
            $status = is_object($appt->status) ? $appt->status->value : $appt->status;

            return response()->json(['type' => 'token', 'token' => [
                'token'      => $appt->notes,
                'patient'    => $appt->patient?->name,
                'patient_id' => $appt->patient_id,
                'doctor'     => $appt->doctor?->name,
                'department' => $appt->doctor?->department,
                'time'       => optional($appt->slot_start)->format('M d, H:i'),
                'status'     => $status,
                'position'   => $this->queuePosition($hid, $appt, $status),
            ]]);
        }

        $patients = \Illuminate\Support\Facades\DB::table('patients')->where('hospital_id', $hid)->whereNull('deleted_at')
            ->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
            ->orderBy('name')->limit(12)->get(['id', 'name', 'phone']);

        return response()->json(['type' => 'patients', 'results' => $patients]);
    }

    /** Unified snapshot: identity + appointments/queue + admission + billing. */
    public function infoDeskPatient(string $id)
    {
        $hid = Auth::user()->hospital_id;
        $patient = \App\Modules\Patient\Models\Patient::where('hospital_id', $hid)->find($id);
        if (! $patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        $appts = \App\Modules\Appointment\Models\Appointment::where('hospital_id', $hid)->where('patient_id', $id)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->whereDate('slot_start', '>=', today())
            ->with('doctor:id,name,department')->orderBy('slot_start')->limit(10)->get()
            ->map(function ($a) use ($hid) {
                $status = is_object($a->status) ? $a->status->value : $a->status;

                return [
                    'doctor'     => $a->doctor?->name,
                    'department' => $a->doctor?->department,
                    'date'       => optional($a->slot_start)->format('M d'),
                    'time'       => optional($a->slot_start)->format('H:i'),
                    'token'      => $a->notes,
                    'status'     => $status,
                    'position'   => $this->queuePosition($hid, $a, $status),
                ];
            });

        $adm = \App\Modules\Inpatient\Models\Admission::where('hospital_id', $hid)->where('patient_id', $id)
            ->whereNull('discharged_at')->with(['ward:id,name', 'bed:id,bed_number', 'admittingDoctor:id,name'])
            ->latest('admitted_at')->first();
        $admission = $adm ? [
            'ward'   => $adm->ward?->name,
            'bed'    => $adm->bed?->bed_number,
            'doctor' => $adm->admittingDoctor?->name,
            'since'  => optional($adm->admitted_at)->format('M d, Y'),
            'status' => $adm->status,
            'reason' => $adm->reason,
        ] : null;

        $bills = \App\Modules\Billing\Models\Bill::where('hospital_id', $hid)->where('patient_id', $id)->get();
        $outstanding = round($bills->sum(fn ($b) => (float) $b->balance_due), 2);
        $pending = $bills->filter(fn ($b) => (float) $b->balance_due > 0.009)->count();

        $lastEnc = \Illuminate\Support\Facades\DB::table('encounters')->where('hospital_id', $hid)->where('patient_id', $id)->max('created_at');

        return response()->json([
            'patient' => [
                'id'          => $patient->id,
                'name'        => $patient->name,
                'phone'       => $patient->phone,
                'gender'      => $patient->gender,
                'blood_group' => $patient->blood_group,
                'health_id'   => $patient->abha_number,
                'age'         => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : null,
            ],
            'appointments' => $appts,
            'admission'    => $admission,
            'billing'      => ['outstanding' => $outstanding, 'pending_bills' => $pending, 'currency' => \App\Modules\Core\Services\RegionService::currency()],
            'last_visit'   => $lastEnc ? \Carbon\Carbon::parse($lastEnc)->format('M d, Y') : null,
        ]);
    }

    /** Rough live queue position for a checked-in / in-progress appointment. */
    private function queuePosition(string $hid, $appt, string $status): ?int
    {
        if (! in_array($status, ['checked_in', 'in_progress'], true)) {
            return null;
        }
        $ref = $appt->check_in_time ?? $appt->slot_start;

        return \App\Modules\Appointment\Models\Appointment::where('hospital_id', $hid)
            ->where('doctor_id', $appt->doctor_id)
            ->whereDate('slot_start', optional($appt->slot_start)->toDateString() ?: today()->toDateString())
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->where(function ($x) use ($ref) {
                $x->where('slot_start', '<=', $ref)->orWhere('check_in_time', '<=', $ref);
            })
            ->count();
    }

    public function tests(Request $request)
    {
        $tests = \DB::table('available_tests')
            ->where('is_active', true)
            ->orderBy('type')->orderBy('name')
            ->get();

        return view('admin.tests', compact('tests'));
    }

    public function storeTest(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:lab,imaging,procedure',
            'category' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'turnaround_time' => 'nullable|string|max:100',
            'instructions' => 'nullable|string|max:500',
        ]);

        \DB::table('available_tests')->insert([
            'id' => \Str::uuid()->toString(),
            'hospital_id' => Auth::user()->hospital_id,
            'name' => $v['name'],
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $v['name']), 0, 6)),
            'type' => $v['type'],
            'category' => $v['category'] ?? $v['type'],
            'price' => $v['price'] ?? 0,
            'turnaround_time' => $v['turnaround_time'] ?? null,
            'instructions' => $v['instructions'] ?? null,
            'is_global' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('web.admin.tests')->with('success', 'Test added successfully.');
    }

    public function deleteTest(string $id)
    {
        \DB::table('available_tests')->where('id', $id)->update(['is_active' => false]);
        return redirect()->route('web.admin.tests')->with('success', 'Test removed.');
    }

    public function medicines(Request $request)
    {
        $medicines = \DB::table('medicines')
            ->where('is_active', true)
            ->orderBy('category')->orderBy('name')
            ->get();

        return view('admin.medicines', compact('medicines'));
    }

    public function storeMedicine(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'default_dosage' => 'nullable|string|max:50',
            'form' => 'nullable|string|max:50',
        ]);

        \DB::table('medicines')->insert([
            'id' => \Str::uuid()->toString(),
            'hospital_id' => Auth::user()->hospital_id,
            'name' => $v['name'],
            'generic_name' => $v['generic_name'] ?? $v['name'],
            'category' => $v['category'] ?? 'Other',
            'default_dosage' => $v['default_dosage'] ?? null,
            'form' => $v['form'] ?? 'tablet',
            'is_global' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('web.admin.medicines')->with('success', 'Medicine added.');
    }

    public function deleteMedicine(string $id)
    {
        \DB::table('medicines')->where('id', $id)->update(['is_active' => false]);
        return redirect()->route('web.admin.medicines')->with('success', 'Medicine removed.');
    }

    public function slots()
    {
        $doctors = Staff::where('hospital_id', Auth::user()->hospital_id)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'specialization', 'schedule', 'consultation_duration_default']);

        return view('admin.slots', compact('doctors'));
    }

    public function updateSlots(Request $request, string $staffId)
    {
        $request->validate([
            'schedule' => 'present',
            'consultation_duration_default' => 'nullable|integer|min:5|max:120',
        ]);

        $staff = Staff::where('hospital_id', Auth::user()->hospital_id)->findOrFail($staffId);
        $staff->schedule = $request->input('schedule');
        if ($request->has('consultation_duration_default')) {
            $staff->consultation_duration_default = $request->input('consultation_duration_default');
        }
        $staff->save();

        return response()->json(['success' => true, 'message' => 'Schedule updated.']);
    }

    public function analytics(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $period = $request->get('period', 'this_month');

        // Determine date ranges based on period
        switch ($period) {
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth();
                $endDate = now()->subMonth()->endOfMonth();
                $prevStart = now()->subMonths(2)->startOfMonth();
                $prevEnd = now()->subMonths(2)->endOfMonth();
                $periodLabel = 'Last Month';
                break;
            case 'last_3_months':
                $startDate = now()->subMonths(3)->startOfMonth();
                $endDate = now()->endOfDay();
                $prevStart = now()->subMonths(6)->startOfMonth();
                $prevEnd = now()->subMonths(3)->startOfMonth();
                $periodLabel = 'Last 3 Months';
                break;
            case 'this_year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfDay();
                $prevStart = now()->subYear()->startOfYear();
                $prevEnd = now()->subYear()->endOfYear();
                $periodLabel = 'This Year';
                break;
            default: // this_month
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfDay();
                $prevStart = now()->subMonth()->startOfMonth();
                $prevEnd = now()->subMonth()->endOfMonth();
                $periodLabel = 'This Month';
                break;
        }

        // Patient counts
        $patientsThisPeriod = Patient::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $patientsPrevPeriod = Patient::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        // Revenue
        $revenueThisPeriod = Bill::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount') ?? 0;
        $revenuePrevPeriod = Bill::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount') ?? 0;

        // Appointments
        $appointmentsThisPeriod = Appointment::where('hospital_id', $hospitalId)
            ->whereBetween('slot_start', [$startDate, $endDate])
            ->count();
        $appointmentsPrevPeriod = Appointment::where('hospital_id', $hospitalId)
            ->whereBetween('slot_start', [$prevStart, $prevEnd])
            ->count();

        // Avg wait time (SQLite-compatible)
        $avgWaitTime = 0;
        $waitEntries = QueueEntry::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('called_at')
            ->get(['created_at', 'called_at']);
        if ($waitEntries->count() > 0) {
            $totalMinutes = $waitEntries->sum(fn ($e) => $e->called_at->diffInMinutes($e->created_at));
            $avgWaitTime = round($totalMinutes / $waitEntries->count());
        }

        // Top 5 doctors by patient count
        $topDoctors = Appointment::where('appointments.hospital_id', $hospitalId)
            ->whereBetween('slot_start', [$startDate, $endDate])
            ->join('staff', 'appointments.doctor_id', '=', 'staff.id')
            ->selectRaw('staff.name, staff.department, staff.specialization, count(distinct appointments.patient_id) as patient_count, count(*) as appointment_count')
            ->groupBy('staff.id', 'staff.name', 'staff.department', 'staff.specialization')
            ->orderByDesc('patient_count')
            ->limit(5)
            ->get();

        // Department-wise patient distribution
        $departmentStats = Appointment::where('appointments.hospital_id', $hospitalId)
            ->whereBetween('slot_start', [$startDate, $endDate])
            ->join('staff', 'appointments.doctor_id', '=', 'staff.id')
            ->selectRaw("coalesce(staff.department, 'General') as department, count(distinct appointments.patient_id) as patient_count")
            ->groupBy('staff.department')
            ->orderByDesc('patient_count')
            ->get();

        // Recent 20 encounters
        $recentEncounters = Encounter::where('encounters.hospital_id', $hospitalId)
            ->with(['patient', 'doctor'])
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.analytics', compact(
            'period', 'periodLabel',
            'patientsThisPeriod', 'patientsPrevPeriod',
            'revenueThisPeriod', 'revenuePrevPeriod',
            'appointmentsThisPeriod', 'appointmentsPrevPeriod',
            'avgWaitTime',
            'topDoctors', 'departmentStats', 'recentEncounters',
        ));
    }

    public function updateTest(Request $request, string $id)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'turnaround_time' => 'nullable|string|max:100',
            'instructions' => 'nullable|string|max:500',
        ]);

        \DB::table('available_tests')->where('id', $id)->update([
            'name' => $v['name'],
            'price' => $v['price'] ?? 0,
            'turnaround_time' => $v['turnaround_time'] ?? null,
            'instructions' => $v['instructions'] ?? null,
            'updated_at' => now(),
        ]);

        return redirect()->route('web.admin.tests')->with('success', 'Test updated successfully.');
    }

    public function updateMedicine(Request $request, string $id)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'default_dosage' => 'nullable|string|max:50',
            'form' => 'nullable|string|max:50',
        ]);

        \DB::table('medicines')->where('id', $id)->update([
            'name' => $v['name'],
            'generic_name' => $v['generic_name'] ?? $v['name'],
            'category' => $v['category'] ?? 'Other',
            'default_dosage' => $v['default_dosage'] ?? null,
            'form' => $v['form'] ?? 'tablet',
            'updated_at' => now(),
        ]);

        return redirect()->route('web.admin.medicines')->with('success', 'Medicine updated successfully.');
    }

    // ---------------------------------------------------------------
    // Staff CRUD
    // ---------------------------------------------------------------

    public function storeStaff(Request $request)
    {
        $v = $request->validate([
            'name'                          => 'required|string|max:255',
            'email'                         => 'required|email|max:255|unique:staff,email',
            'phone'                         => 'nullable|string|max:20',
            'role'                          => 'required|in:doctor,nurse,receptionist,pharmacist,lab_tech,billing_staff,hospital_admin',
            'department'                    => 'nullable|string|max:100',
            'specialization'                => 'nullable|string|max:100',
            'qualification'                 => 'nullable|string|max:255',
            'consultation_duration_default' => 'nullable|integer|min:5|max:120',
            'password'                      => 'nullable|string|min:6',
        ]);

        $hospitalId = Auth::user()->hospital_id;
        $staffId = Str::uuid()->toString();
        $userId = Str::uuid()->toString();
        $plain = $request->filled('password') ? $v['password'] : Str::random(10);

        \DB::table('staff')->insert([
            'id'                            => $staffId,
            'hospital_id'                   => $hospitalId,
            'name'                          => $v['name'],
            'email'                         => $v['email'],
            'phone'                         => $v['phone'] ?? null,
            'role'                          => $v['role'],
            'department'                    => $v['department'] ?? null,
            'specialization'                => $v['specialization'] ?? null,
            'qualification'                 => $v['qualification'] ?? null,
            'consultation_duration_default' => $v['consultation_duration_default'] ?? 15,
            'is_active'                     => true,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ]);

        \DB::table('users')->insert([
            'id'          => $userId,
            'name'        => $v['name'],
            'email'       => $v['email'],
            'password'    => \Illuminate\Support\Facades\Hash::make($plain),
            'phone'       => $v['phone'] ?? null,
            'role'        => $v['role'],
            'hospital_id' => $hospitalId,
            'staff_id'    => $staffId,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Link user_id back to staff
        \DB::table('staff')->where('id', $staffId)->update(['user_id' => $userId]);

        return redirect()->route('web.admin.staff')->with('success',
            "Staff \"{$v['name']}\" added. Login → {$v['email']} / {$plain}  (share securely; they should change it).");
    }

    public function updateStaff(Request $request, string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        $staff = \DB::table('staff')->where('id', $id)->where('hospital_id', $hospitalId)->first();

        if (!$staff) {
            abort(404);
        }

        $v = $request->validate([
            'name'                          => 'required|string|max:255',
            'email'                         => 'required|email|max:255|unique:staff,email,' . $id,
            'phone'                         => 'nullable|string|max:20',
            'role'                          => 'required|in:doctor,nurse,receptionist,pharmacist,lab_tech,billing_staff,hospital_admin',
            'department'                    => 'nullable|string|max:100',
            'specialization'                => 'nullable|string|max:100',
            'qualification'                 => 'nullable|string|max:255',
            'consultation_duration_default' => 'nullable|integer|min:5|max:120',
        ]);

        \DB::table('staff')->where('id', $id)->update([
            'name'                          => $v['name'],
            'email'                         => $v['email'],
            'phone'                         => $v['phone'] ?? null,
            'role'                          => $v['role'],
            'department'                    => $v['department'] ?? null,
            'specialization'                => $v['specialization'] ?? null,
            'qualification'                 => $v['qualification'] ?? null,
            'consultation_duration_default' => $v['consultation_duration_default'] ?? 15,
            'updated_at'                    => now(),
        ]);

        return redirect()->route('web.admin.staff')->with('success', 'Staff member updated.');
    }

    public function deleteStaff(string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        \DB::table('staff')->where('id', $id)->where('hospital_id', $hospitalId)->update([
            'is_active'  => false,
            'updated_at' => now(),
        ]);

        return redirect()->route('web.admin.staff')->with('success', 'Staff member deactivated.');
    }

    /**
     * Reset a staff member's login password (generates a new temporary one and
     * shows it). Creates the login account if the staff has none yet.
     */
    public function resetStaffPassword(string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        $staff = \DB::table('staff')->where('id', $id)->where('hospital_id', $hospitalId)->first();
        if (! $staff) {
            abort(404);
        }

        $plain = \Illuminate\Support\Str::random(10);
        $hash  = \Illuminate\Support\Facades\Hash::make($plain);

        $userId = $staff->user_id ?: (\DB::table('users')->where('email', $staff->email)->value('id'));

        if ($userId) {
            \DB::table('users')->where('id', $userId)->update([
                'password' => $hash, 'is_active' => true, 'updated_at' => now(),
            ]);
        } else {
            // No login yet — create one so they can sign in.
            $userId = \Illuminate\Support\Str::uuid()->toString();
            \DB::table('users')->insert([
                'id'          => $userId,
                'name'        => $staff->name,
                'email'       => $staff->email,
                'password'    => $hash,
                'phone'       => $staff->phone ?? null,
                'role'        => $staff->role,
                'hospital_id' => $hospitalId,
                'staff_id'    => $staff->id,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
        \DB::table('staff')->where('id', $staff->id)->update(['user_id' => $userId]);

        return redirect()->route('web.admin.staff')->with('success',
            "New password for {$staff->name} → {$staff->email} / {$plain}  (share securely; they should change it).");
    }

    public function activateStaff(string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        \DB::table('staff')->where('id', $id)->where('hospital_id', $hospitalId)->update([
            'is_active'  => true,
            'updated_at' => now(),
        ]);

        return redirect()->route('web.admin.staff')->with('success', 'Staff member activated.');
    }

    // ---------------------------------------------------------------
    // Appointment Check-In & Cancel
    // ---------------------------------------------------------------

    public function checkInAppointment(string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        $updated = \DB::table('appointments')
            ->where('id', $id)
            ->where('hospital_id', $hospitalId)
            ->update([
                'status'        => 'checked_in',
                'check_in_time' => now(),
                'updated_at'    => now(),
            ]);

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function cancelAppointment(Request $request, string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        $apt = Appointment::where('id', $id)->where('hospital_id', $hospitalId)->first();

        if (!$apt) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        $reason = $request->input('cancellation_reason', 'Cancelled by hospital');
        $apt->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        // Notify patient via WhatsApp
        \App\Modules\Core\Services\WhatsAppNotifier::appointmentCancelled($apt, 'Hospital', $reason);

        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Reception appointment scheduling (walk-in + online sync)
    // ---------------------------------------------------------------

    /** Reception booking screen. */
    public function scheduleForm(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $doctors = Staff::where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'consultation_duration_default']);

        return view('admin.appointment-schedule', [
            'doctors'       => $doctors,
            'prefillDoctor' => $request->get('doctor'),
        ]);
    }

    /** Book a walk-in / phone appointment into the shared appointments pool. */
    public function storeAppointment(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;

        $v = $request->validate([
            'patient_id'  => 'nullable|uuid|exists:patients,id',
            'new_name'    => 'nullable|string|max:255',
            'new_phone'   => 'nullable|string|max:20',
            'doctor_id'   => 'required|uuid|exists:staff,id',
            'walk_in_now' => 'nullable|boolean',
            'slot_start'  => 'nullable|date',
            'reason'      => 'nullable|string|max:255',
        ]);

        // Resolve the patient — existing, or quick-register a new one.
        $patientId = $v['patient_id'] ?? null;
        if (! $patientId) {
            if (empty($v['new_name']) || empty($v['new_phone'])) {
                return back()->withInput()->with('error', 'Select an existing patient or enter a new patient name + phone.');
            }
            $phone = preg_replace('/\s+/', '', $v['new_phone']);
            if (preg_match('/^\d{10}$/', $phone)) {
                $phone = '+91' . $phone;
            }
            $existing = Patient::withTrashed()->where('hospital_id', $hospitalId)->where('phone', $phone)->first();
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $patientId = $existing->id;
            } else {
                $patientId = Patient::create([
                    'id'          => Str::uuid()->toString(),
                    'hospital_id' => $hospitalId,
                    'name'        => $v['new_name'],
                    'phone'       => $phone,
                    'created_via' => 'reception',
                ])->id;
            }
        }

        $doctor = Staff::where('hospital_id', $hospitalId)->findOrFail($v['doctor_id']);
        $duration = $doctor->consultation_duration_default ?? 15;
        $walkNow = (bool) ($v['walk_in_now'] ?? false);

        if ($walkNow) {
            $slotStart = now();
            $status = 'checked_in';
        } else {
            if (empty($v['slot_start'])) {
                return back()->withInput()->with('error', 'Pick a time slot, or choose "Walk-in now".');
            }
            $slotStart = \Carbon\Carbon::parse($v['slot_start']);
            if ($slotStart->lt(now())) {
                return back()->withInput()->with('error', 'That time has already passed.');
            }
            $taken = Appointment::where('doctor_id', $doctor->id)
                ->where('slot_start', $slotStart)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->exists();
            if ($taken) {
                return back()->withInput()->with('error', 'That slot is already booked — pick another.');
            }
            $status = 'scheduled';
        }

        // Duplicate guard: same patient already booked with this doctor that day.
        $dupe = Appointment::where('hospital_id', $hospitalId)
            ->where('patient_id', $patientId)
            ->where('doctor_id', $doctor->id)
            ->whereDate('slot_start', $slotStart->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();
        if ($dupe) {
            return back()->withInput()->with('error', 'This patient already has an appointment with ' . $doctor->name . ' on ' . $slotStart->format('M d') . '.');
        }

        $encounter = Encounter::create([
            'id'               => Str::uuid()->toString(),
            'hospital_id'      => $hospitalId,
            'patient_id'       => $patientId,
            'doctor_id'        => $doctor->id,
            'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'type'             => 'consultation',
            'status'           => 'booked',
            'channel'          => 'walk_in',
            'intake_data'      => array_filter([
                'chief_complaint' => $v['reason'] ?? null,
                'source'          => 'reception',
            ], fn ($x) => $x !== null),
        ]);

        $token = Appointment::generateToken($doctor->id, $doctor->department, $slotStart);

        $apt = Appointment::create([
            'id'                         => Str::uuid()->toString(),
            'hospital_id'                => $hospitalId,
            'encounter_id'               => $encounter->id,
            'patient_id'                 => $patientId,
            'doctor_id'                  => $doctor->id,
            'status'                     => $status,
            'slot_start'                 => $slotStart,
            'slot_end'                   => $slotStart->copy()->addMinutes($duration),
            'predicted_duration_minutes' => $duration,
            'booking_source'             => 'walk_in',
            'notes'                      => $token,
            'check_in_time'              => $walkNow ? now() : null,
        ]);

        // Confirmation to patient — same WhatsApp notice online bookings get (skip for walk-in-now, patient is present).
        if (! $walkNow) {
            try {
                \App\Modules\Core\Services\WhatsAppNotifier::appointmentBooked($apt->load('patient', 'doctor'));
            } catch (\Throwable $e) {
                \Log::warning('[Appointments] booking notify failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('web.admin.appointments', ['date' => $slotStart->toDateString()])
            ->with('success', 'Appointment booked — Token ' . $token . ($walkNow
                ? ' (checked in — now in the queue).'
                : ' for ' . $slotStart->format('M d, g:i A') . '.'));
    }

    /** Move an appointment to a different slot. */
    public function rescheduleAppointment(Request $request, string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        $apt = Appointment::where('hospital_id', $hospitalId)->findOrFail($id);

        $request->validate(['slot_start' => 'required|date']);
        $newStart = \Carbon\Carbon::parse($request->slot_start);

        if ($newStart->lt(now())) {
            return back()->with('error', 'That time has already passed.');
        }
        $taken = Appointment::where('doctor_id', $apt->doctor_id)
            ->where('slot_start', $newStart)
            ->where('id', '!=', $apt->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();
        if ($taken) {
            return back()->with('error', 'That slot is already booked.');
        }

        $oldSlot = optional($apt->slot_start)->format('M d, g:i A') ?? '';
        $duration = $apt->predicted_duration_minutes ?? 15;
        $apt->update([
            'slot_start' => $newStart,
            'slot_end'   => $newStart->copy()->addMinutes($duration),
            'status'     => 'scheduled',
        ]);

        try {
            \App\Modules\Core\Services\WhatsAppNotifier::appointmentRescheduled($apt->load('patient', 'doctor'), $oldSlot);
        } catch (\Throwable $e) {
            \Log::warning('[Appointments] reschedule notify failed: ' . $e->getMessage());
        }

        return redirect()->route('web.admin.appointments', ['date' => $newStart->toDateString()])
            ->with('success', 'Appointment rescheduled to ' . $newStart->format('M d, g:i A') . '.');
    }

    /** Mark an appointment as no-show. */
    public function noShowAppointment(string $id)
    {
        $updated = \DB::table('appointments')
            ->where('id', $id)
            ->where('hospital_id', Auth::user()->hospital_id)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->update(['status' => 'no_show', 'updated_at' => now()]);

        if (! $updated) {
            return response()->json(['success' => false, 'message' => 'This appointment cannot be marked no-show.'], 422);
        }

        return response()->json(['success' => true]);
    }

    /** AJAX: upcoming (future, active) appointments for a patient — powers the duplicate-booking warning. */
    public function patientUpcoming(Request $request)
    {
        $request->validate(['patient' => 'required|uuid']);
        $rows = Appointment::where('hospital_id', Auth::user()->hospital_id)
            ->where('patient_id', $request->patient)
            ->where('slot_start', '>=', now()->startOfDay())
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->with('doctor:id,name')
            ->orderBy('slot_start')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'doctor' => $a->doctor?->name ?? 'Doctor',
                'when'   => optional($a->slot_start)->format('M d, g:i A'),
                'token'  => $a->notes,
            ]);

        return response()->json(['upcoming' => $rows]);
    }

    // ---------------------------------------------------------------
    // Payment & Token counter (OPD registration / fee collection)
    // ---------------------------------------------------------------

    /** The reception payment + token counter. */
    public function counter()
    {
        $hospitalId = Auth::user()->hospital_id;
        $doctors = Staff::where('hospital_id', $hospitalId)->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])->orderBy('name')
            ->get(['id', 'name', 'department', 'consultation_duration_default']);
        $services = \App\Modules\Billing\Models\ServiceCharge::where('hospital_id', $hospitalId)
            ->where('is_active', true)->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'category', 'price']);

        return view('admin.counter', compact('doctors', 'services'));
    }

    /** Collect the fee, issue a token, enter the doctor queue, and produce a slip. */
    public function issueToken(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $v = $request->validate([
            'patient_id'          => 'nullable|uuid|exists:patients,id',
            'new_name'            => 'nullable|string|max:255',
            'new_phone'           => 'nullable|string|max:20',
            'doctor_id'           => 'nullable|uuid|exists:staff,id',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.price'       => 'required|numeric|min:0',
            'items.*.quantity'    => 'nullable|numeric|min:0.01',
            'method'              => 'required|in:cash,upi,card,bank,cheque',
            'reference'           => 'nullable|string|max:255',
        ]);

        // Resolve patient — existing or quick-register.
        $patientId = $v['patient_id'] ?? null;
        if (! $patientId) {
            if (empty($v['new_name']) || empty($v['new_phone'])) {
                return back()->withInput()->with('error', 'Select a patient or enter a new patient name + phone.');
            }
            $phone = preg_replace('/\s+/', '', $v['new_phone']);
            if (preg_match('/^\d{10}$/', $phone)) {
                $phone = '+91' . $phone;
            }
            $existing = Patient::withTrashed()->where('hospital_id', $hospitalId)->where('phone', $phone)->first();
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $patientId = $existing->id;
            } else {
                $patientId = Patient::create([
                    'id' => Str::uuid()->toString(), 'hospital_id' => $hospitalId,
                    'name' => $v['new_name'], 'phone' => $phone, 'created_via' => 'reception',
                ])->id;
            }
        }

        $items = collect($v['items'])->map(function ($i) {
            $qty = (float) ($i['quantity'] ?? 1);
            $price = (float) $i['price'];
            return ['description' => $i['description'], 'quantity' => $qty, 'unit_price' => $price, 'total' => round($qty * $price, 2)];
        })->values()->all();
        $total = round(collect($items)->sum('total'), 2);

        if ($total <= 0) {
            return back()->withInput()->with('error', 'Add at least one charge with an amount greater than zero.');
        }

        $doctor = ! empty($v['doctor_id']) ? Staff::where('hospital_id', $hospitalId)->find($v['doctor_id']) : null;
        $now = now();

        // Guard: don't double-queue the same patient with the same doctor on the same day.
        if ($doctor) {
            $existingApt = Appointment::where('hospital_id', $hospitalId)
                ->where('patient_id', $patientId)
                ->where('doctor_id', $doctor->id)
                ->whereDate('slot_start', today())
                ->whereIn('status', ['checked_in', 'in_progress'])
                ->first();
            if ($existingApt) {
                return back()->withInput()->with('error',
                    'This patient is already in ' . $doctor->name . "'s queue (token " . ($existingApt->notes ?: '—') . '). Complete or cancel that visit before issuing a new token.');
            }
        }

        $encounter = Encounter::create([
            'id'               => Str::uuid()->toString(),
            'hospital_id'      => $hospitalId,
            'patient_id'       => $patientId,
            'doctor_id'        => $doctor?->id,
            'encounter_number' => 'ENC-' . $now->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'type'             => 'consultation',
            'status'           => $doctor ? 'checked_in' : 'completed',
            'channel'          => 'walk_in',
            'intake_data'      => ['source' => 'counter'],
        ]);

        if ($doctor) {
            $duration = $doctor->consultation_duration_default ?? 15;
            $token = Appointment::generateToken($doctor->id, $doctor->department, $now);
            Appointment::create([
                'id'                         => Str::uuid()->toString(),
                'hospital_id'                => $hospitalId,
                'encounter_id'               => $encounter->id,
                'patient_id'                 => $patientId,
                'doctor_id'                  => $doctor->id,
                'status'                     => 'checked_in',
                'slot_start'                 => $now,
                'slot_end'                   => $now->copy()->addMinutes($duration),
                'predicted_duration_minutes' => $duration,
                'booking_source'             => 'counter',
                'notes'                      => $token,
                'check_in_time'              => $now,
            ]);
        } else {
            $seq = Bill::where('hospital_id', $hospitalId)->where('bill_type', 'counter')
                ->whereDate('created_at', today())->count() + 1;
            $token = 'OP-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        $bill = Bill::create([
            'id'                => Str::uuid()->toString(),
            'hospital_id'       => $hospitalId,
            'encounter_id'      => $encounter->id,
            'patient_id'        => $patientId,
            'bill_number'       => 'RCP-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'bill_type'         => 'counter',
            'line_items'        => $items,
            'subtotal'          => $total,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'insurance_covered' => 0,
            'total_amount'      => $total,
            'patient_payable'   => $total,
            'amount_paid'       => $total,
            'balance_due'       => 0,
            'payment_status'    => 'paid',
            'payment_method'    => $v['method'],
            'payment_reference' => $v['reference'] ?? null,
            'currency'          => \App\Modules\Core\Services\RegionService::currencyCode(),
            'notes'             => 'Counter token ' . $token,
            'issued_at'         => $now,
            'paid_at'           => $now,
        ]);

        \App\Modules\Billing\Models\BillPayment::create([
            'hospital_id'      => $hospitalId,
            'bill_id'          => $bill->id,
            'amount'           => $total,
            'method'           => $v['method'],
            'reference'        => $v['reference'] ?? null,
            'received_by'      => Auth::id(),
            'received_by_name' => Auth::user()->name ?? 'Staff',
            'paid_at'          => $now,
        ]);

        return redirect()->route('web.admin.counter.slip', $bill->id)
            ->with('success', 'Token ' . $token . ' issued — ' . \App\Modules\Core\Services\RegionService::currency() . number_format($total, 2) . ' collected.');
    }

    /** Printable token slip for a counter payment. */
    public function tokenSlip(string $id)
    {
        $hospitalId = Auth::user()->hospital_id;
        $bill = Bill::where('hospital_id', $hospitalId)->with('patient')->findOrFail($id);
        $hospital = Hospital::find($hospitalId);

        $appointment = Appointment::where('encounter_id', $bill->encounter_id)->first();
        $doctor = $appointment?->doctor_id ? Staff::find($appointment->doctor_id) : null;
        $token = $appointment?->notes ?? trim(str_replace('Counter token', '', (string) $bill->notes));

        $position = null;
        if ($appointment) {
            $position = Appointment::where('doctor_id', $appointment->doctor_id)
                ->whereDate('slot_start', today())
                ->whereIn('status', ['checked_in', 'in_progress'])
                ->where('slot_start', '<=', $appointment->slot_start)
                ->count();
        }

        return view('admin.token-slip', compact('bill', 'hospital', 'token', 'doctor', 'appointment', 'position'));
    }

    // ---------------------------------------------------------------
    // Live queue management (doctor queues + lab queue)
    // ---------------------------------------------------------------

    /** Reception live-queue overview. */
    public function queue()
    {
        $hospitalId = Auth::user()->hospital_id;

        // Doctor queues — today's active appointments grouped by doctor.
        $doctorQueue = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', today())
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->with(['patient', 'doctor'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'checked_in' THEN 1 ELSE 2 END")
            ->orderBy('slot_start')
            ->get()
            ->groupBy(fn ($a) => $a->doctor?->name ?? 'Unassigned');

        // Lab queue — active lab orders.
        $labQueue = \App\Modules\Core\Models\Order::where('hospital_id', $hospitalId)
            ->where('type', 'lab')
            ->whereIn('status', ['ordered', 'accepted', 'in_progress'])
            ->with('patient')
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();

        $doctors = Staff::where('hospital_id', $hospitalId)->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])->orderBy('name')
            ->get(['id', 'name', 'department', 'consultation_duration_default']);

        $tests = $this->queueTests();

        return view('admin.queue', compact('doctorQueue', 'labQueue', 'doctors', 'tests'));
    }

    /** Available tests for the lab-queue picker (safe against schema variance). */
    private function queueTests()
    {
        try {
            return \Illuminate\Support\Facades\DB::table('available_tests')
                ->orderBy('name')->limit(400)->get(['id', 'name', 'price']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Add a walk-in patient to a doctor's queue or the lab queue. */
    public function addToQueue(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;

        $v = $request->validate([
            'target'        => 'required|in:doctor,lab',
            'patient_id'    => 'nullable|uuid|exists:patients,id',
            'new_name'      => 'nullable|string|max:255',
            'new_phone'     => 'nullable|string|max:20',
            'doctor_id'     => 'nullable|uuid|exists:staff,id',
            'reason'        => 'nullable|string|max:255',
            'tests'         => 'nullable|array',
            'tests.*.name'  => 'required_with:tests|string|max:255',
            'tests.*.price' => 'nullable|numeric',
            'priority'      => 'nullable|in:routine,urgent,stat',
        ]);

        // Resolve patient — existing or quick-register.
        $patientId = $v['patient_id'] ?? null;
        if (! $patientId) {
            if (empty($v['new_name']) || empty($v['new_phone'])) {
                return back()->withInput()->with('error', 'Select a patient or enter a new patient name + phone.');
            }
            $phone = preg_replace('/\s+/', '', $v['new_phone']);
            if (preg_match('/^\d{10}$/', $phone)) {
                $phone = '+91' . $phone;
            }
            $existing = Patient::withTrashed()->where('hospital_id', $hospitalId)->where('phone', $phone)->first();
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $patientId = $existing->id;
            } else {
                $patientId = Patient::create([
                    'id'          => Str::uuid()->toString(),
                    'hospital_id' => $hospitalId,
                    'name'        => $v['new_name'],
                    'phone'       => $phone,
                    'created_via' => 'reception',
                ])->id;
            }
        }

        if ($v['target'] === 'doctor') {
            if (empty($v['doctor_id'])) {
                return back()->withInput()->with('error', 'Pick a doctor.');
            }
            $doctor = Staff::where('hospital_id', $hospitalId)->findOrFail($v['doctor_id']);
            $duration = $doctor->consultation_duration_default ?? 15;
            $slotStart = now();

            $dupe = Appointment::where('hospital_id', $hospitalId)
                ->where('patient_id', $patientId)->where('doctor_id', $doctor->id)
                ->whereDate('slot_start', today())
                ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
                ->exists();
            if ($dupe) {
                return back()->with('error', 'This patient is already in ' . $doctor->name . "'s queue today.");
            }

            $encounter = Encounter::create([
                'id'               => Str::uuid()->toString(),
                'hospital_id'      => $hospitalId,
                'patient_id'       => $patientId,
                'doctor_id'        => $doctor->id,
                'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                'type'             => 'consultation',
                'status'           => 'checked_in',
                'channel'          => 'walk_in',
                'intake_data'      => array_filter([
                    'chief_complaint' => $v['reason'] ?? null,
                    'source'          => 'reception_queue',
                ], fn ($x) => $x !== null),
            ]);

            $token = Appointment::generateToken($doctor->id, $doctor->department, $slotStart);
            Appointment::create([
                'id'                         => Str::uuid()->toString(),
                'hospital_id'                => $hospitalId,
                'encounter_id'               => $encounter->id,
                'patient_id'                 => $patientId,
                'doctor_id'                  => $doctor->id,
                'status'                     => 'checked_in',
                'slot_start'                 => $slotStart,
                'slot_end'                   => $slotStart->copy()->addMinutes($duration),
                'predicted_duration_minutes' => $duration,
                'booking_source'             => 'walk_in',
                'notes'                      => $token,
                'check_in_time'              => now(),
            ]);

            return redirect()->route('web.admin.queue')
                ->with('success', 'Added to ' . $doctor->name . "'s queue — Token " . $token . '.');
        }

        // Lab queue
        $tests = collect($v['tests'] ?? [])
            ->filter(fn ($t) => ! empty($t['name']))
            ->map(fn ($t) => ['name' => $t['name'], 'price' => isset($t['price']) ? (float) $t['price'] : 0])
            ->values()->all();
        if (empty($tests)) {
            return back()->withInput()->with('error', 'Pick at least one test for the lab queue.');
        }

        $token = 'LAB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        \App\Modules\Core\Models\Order::create([
            'id'             => Str::uuid()->toString(),
            'hospital_id'    => $hospitalId,
            'patient_id'     => $patientId,
            'encounter_id'   => null,
            'ordered_by'     => null,
            'type'           => 'lab',
            'status'         => 'ordered',
            'items'          => $tests,
            'priority'       => $v['priority'] ?? 'routine',
            'booking_source' => 'reception_queue',
            'notes'          => $token,
        ]);

        return redirect()->route('web.admin.queue')
            ->with('success', 'Added to lab queue — Token ' . $token . ' (' . count($tests) . ' test(s)).');
    }
}

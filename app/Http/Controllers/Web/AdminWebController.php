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
            ->whereIn('role', ['doctor', 'hospital_admin'])
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
            'insurancePending' => 0,
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

        return view('admin.dashboard', compact(
            'patientsToday', 'avgWaitTime', 'aiRate', 'revenueToday',
            'queues', 'recentActivity', 'quickStats',
        ));
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

    public function storePatient(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'gender' => 'nullable|string|in:male,female,other',
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date',
            'language_preference' => 'nullable|string|max:10',
            'blood_group' => 'nullable|string|max:10',
        ]);

        // Normalize phone: add +91 if 10 digits
        $phone = preg_replace('/\s+/', '', $v['phone']);
        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '+91' . $phone;
        }

        $hospitalId = Auth::user()->hospital_id;

        // Check duplicate by phone in same hospital
        $exists = Patient::where('hospital_id', $hospitalId)->where('phone', $phone)->exists();
        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'A patient with this phone number already exists.');
        }

        Patient::create([
            'id' => Str::uuid()->toString(),
            'hospital_id' => $hospitalId,
            'name' => $v['name'],
            'phone' => $phone,
            'gender' => $v['gender'] ?? null,
            'email' => $v['email'] ?? null,
            'date_of_birth' => $v['date_of_birth'] ?? null,
            'language_preference' => $v['language_preference'] ?? 'en',
            'blood_group' => $v['blood_group'] ?? null,
            'created_via' => 'admin',
        ]);

        return redirect()->route('web.admin.patients')->with('success', 'Patient added successfully.');
    }

    public function updatePatient(Request $request, string $id)
    {
        $patient = Patient::where('hospital_id', Auth::user()->hospital_id)->findOrFail($id);

        $v = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'gender' => 'nullable|string|in:male,female,other',
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date',
            'language_preference' => 'nullable|string|max:10',
            'blood_group' => 'nullable|string|max:10',
        ]);

        // Normalize phone
        $phone = preg_replace('/\s+/', '', $v['phone']);
        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '+91' . $phone;
        }

        $patient->update([
            'name' => $v['name'],
            'phone' => $phone,
            'gender' => $v['gender'] ?? $patient->gender,
            'email' => $v['email'] ?? $patient->email,
            'date_of_birth' => $v['date_of_birth'] ?? $patient->date_of_birth,
            'language_preference' => $v['language_preference'] ?? $patient->language_preference,
            'blood_group' => $v['blood_group'] ?? $patient->blood_group,
        ]);

        return redirect()->back()->with('success', 'Patient updated successfully.');
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
        $date = $request->get('date', today()->toDateString());

        $query = Appointment::where('hospital_id', Auth::user()->hospital_id)
            ->whereDate('slot_start', $date)
            ->with(['patient', 'doctor']);

        if ($doctorId = $request->get('doctor')) {
            $query->where('doctor_id', $doctorId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $appointments = $query->orderBy('slot_start')->paginate(30);

        $doctors = Staff::where('hospital_id', Auth::user()->hospital_id)
            ->where('is_active', true)
            ->whereIn('role', ['doctor', 'hospital_admin'])
            ->orderBy('name')
            ->get();

        return view('admin.appointments', compact('appointments', 'doctors'));
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
        return view('admin.settings', compact('hospital'));
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
            ->whereIn('role', ['doctor', 'hospital_admin'])
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
        ]);

        $hospitalId = Auth::user()->hospital_id;
        $staffId = Str::uuid()->toString();
        $userId = Str::uuid()->toString();

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
            'password'    => \Illuminate\Support\Facades\Hash::make('password123'),
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

        return redirect()->route('web.admin.staff')->with('success', 'Staff member added successfully.');
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
}

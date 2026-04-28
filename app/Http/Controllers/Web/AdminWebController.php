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
            'schedule' => 'required|array',
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

    public function analytics()
    {
        return view('admin.dashboard', [
            'patientsToday' => 0, 'avgWaitTime' => 0, 'aiRate' => 0,
            'revenueToday' => 0, 'queues' => collect(), 'recentActivity' => collect(),
            'quickStats' => ['pendingAppointments' => 0, 'insurancePending' => 0, 'billsUnpaid' => 0, 'consultationsDone' => 0, 'noShows' => 0],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Modules\Patient\Models\Patient;
use App\Modules\Vaccination\Models\PatientVaccination;
use App\Modules\Vaccination\Models\Vaccine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class VaccinationController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();

        $vaccines = Vaccine::where('hospital_id', $hid)->orderBy('category')->orderBy('name')->get();

        // Optional patient focus → DOB-driven immunization schedule.
        $patient = null;
        $schedule = [];
        $history = collect();
        $scheduleSummary = ['overdue' => 0, 'due' => 0, 'upcoming' => 0, 'given' => 0];

        if ($request->filled('patient')) {
            $patient = Patient::where('hospital_id', $hid)->find($request->query('patient'));
            if ($patient) {
                $history = PatientVaccination::where('hospital_id', $hid)->where('patient_id', $patient->id)
                    ->with('vaccine:id,name,route')->orderByDesc('given_date')->get();
                [$schedule, $scheduleSummary] = $this->buildSchedule($patient, $vaccines, $history);
            }
        }

        $records = PatientVaccination::where('hospital_id', $hid)
            ->with(['patient:id,name,phone', 'vaccine:id,name'])
            ->latest('given_date')->limit(50)->get();

        $counts = [
            'given_today' => PatientVaccination::where('hospital_id', $hid)->whereDate('given_date', today())->count(),
            'aefi'        => PatientVaccination::where('hospital_id', $hid)->where('has_aefi', true)->count(),
            'total'       => PatientVaccination::where('hospital_id', $hid)->count(),
        ];

        return view('vaccination.index', compact(
            'vaccines', 'records', 'counts', 'patient', 'schedule', 'scheduleSummary', 'history'
        ));
    }

    /**
     * Compute the age-based immunization schedule for a patient from their DOB.
     * Returns [rows, summary]. Each row = one scheduled dose, its due date, matched to any given record.
     */
    private function buildSchedule(Patient $patient, $vaccines, $history): array
    {
        $summary = ['overdue' => 0, 'due' => 0, 'upcoming' => 0, 'given' => 0];
        $rows = [];

        if (empty($patient->date_of_birth)) {
            return [$rows, $summary];
        }

        $dob = Carbon::parse($patient->date_of_birth)->startOfDay();
        $today = today();
        $givenIndex = [];
        foreach ($history as $h) {
            $givenIndex[$h->vaccine_id . ':' . $h->dose_number] = $h;
        }

        foreach ($vaccines as $vaccine) {
            if (! $vaccine->is_active || ! $vaccine->isScheduled()) {
                continue;
            }
            foreach ($vaccine->age_schedule as $slot) {
                $dose = (int) ($slot['dose'] ?? 0);
                $ageDays = (int) ($slot['age_days'] ?? 0);
                $dueDate = (clone $dob)->addDays($ageDays);
                $given = $givenIndex[$vaccine->id . ':' . $dose] ?? null;

                if ($given) {
                    $status = 'given';
                } elseif ($dueDate->lt($today)) {
                    $status = 'overdue';
                } elseif ($dueDate->lte($today->copy()->addDays(30))) {
                    $status = 'due';
                } else {
                    $status = 'upcoming';
                }
                $summary[$status]++;

                $rows[] = [
                    'vaccine_id' => $vaccine->id,
                    'vaccine'    => $vaccine->name,
                    'route'      => $vaccine->route,
                    'dose'       => $dose,
                    'label'      => $slot['label'] ?? ('Dose ' . $dose),
                    'due_date'   => $dueDate,
                    'status'     => $status,
                    'given_date' => $given?->given_date,
                    'batch'      => $given?->batch_number,
                ];
            }
        }

        // Actionable first (overdue, due), then by due date.
        $order = ['overdue' => 0, 'due' => 1, 'upcoming' => 2, 'given' => 3];
        usort($rows, function ($a, $b) use ($order) {
            if ($order[$a['status']] !== $order[$b['status']]) {
                return $order[$a['status']] <=> $order[$b['status']];
            }
            return $a['due_date'] <=> $b['due_date'];
        });

        return [$rows, $summary];
    }

    public function record(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'    => 'required|uuid',
            'vaccine_id'    => 'required|uuid',
            'dose_number'   => 'required|integer|min:1|max:20',
            'given_date'    => 'required|date',
            'batch_number'  => 'nullable|string|max:60',
            'route'         => 'nullable|in:' . implode(',', array_keys(Vaccine::ROUTES)),
            'site'          => 'nullable|in:' . implode(',', array_keys(PatientVaccination::SITES)),
            'manufacturer'  => 'nullable|string|max:120',
            'expiry_date'   => 'nullable|date',
            'given_by_name' => 'nullable|string|max:120',
            'has_aefi'      => 'nullable|boolean',
            'aefi_notes'    => 'nullable|string|max:500',
            'notes'         => 'nullable|string|max:500',
        ]);

        $vaccine = Vaccine::where('hospital_id', $hid)->find($v['vaccine_id']);
        if (! $vaccine) {
            return back()->with('error', 'Unknown vaccine.');
        }

        // Legacy interval-based next-due (only for on-demand vaccines without an age schedule).
        $nextDue = null;
        if (! $vaccine->isScheduled() && $vaccine->dose_interval_days && $v['dose_number'] < $vaccine->total_doses) {
            $nextDue = Carbon::parse($v['given_date'])->addDays($vaccine->dose_interval_days);
        }

        PatientVaccination::create([
            'hospital_id'    => $hid,
            'patient_id'     => $v['patient_id'],
            'vaccine_id'     => $v['vaccine_id'],
            'dose_number'    => $v['dose_number'],
            'given_date'     => $v['given_date'],
            'batch_number'   => $v['batch_number'] ?? null,
            'route'          => $v['route'] ?? $vaccine->route,
            'site'           => $v['site'] ?? null,
            'manufacturer'   => $v['manufacturer'] ?? null,
            'expiry_date'    => $v['expiry_date'] ?? null,
            'given_by_name'  => $v['given_by_name'] ?? Auth::user()->name,
            'next_due_date'  => $nextDue,
            'next_dose_done' => false,
            'has_aefi'       => (bool) ($v['has_aefi'] ?? false),
            'aefi_notes'     => $v['aefi_notes'] ?? null,
            'notes'          => $v['notes'] ?? null,
        ]);

        PatientVaccination::where('hospital_id', $hid)
            ->where('patient_id', $v['patient_id'])->where('vaccine_id', $v['vaccine_id'])
            ->where('dose_number', $v['dose_number'] - 1)
            ->update(['next_dose_done' => true]);

        return redirect()->route('web.vaccination.index', ['patient' => $v['patient_id']])
            ->with('success', 'Dose ' . $v['dose_number'] . ' of ' . $vaccine->name . ' recorded.');
    }

    public function certificate(string $patientId)
    {
        $hid = $this->hid();
        $patient = Patient::where('hospital_id', $hid)->findOrFail($patientId);
        $doses = PatientVaccination::where('hospital_id', $hid)->where('patient_id', $patient->id)
            ->with('vaccine:id,name,route')->orderBy('given_date')->get();
        $hospital = Auth::user()->hospital ?? null;

        return view('vaccination.certificate', compact('patient', 'doses', 'hospital'));
    }

    public function storeVaccine(Request $request)
    {
        $hid = $this->hid();
        $v = $this->validateVaccine($request);
        $v['hospital_id'] = $hid;
        $v['is_active'] = true;
        Vaccine::create($v);

        return back()->with('success', 'Vaccine added to the master.');
    }

    public function updateVaccine(Request $request, string $id)
    {
        $hid = $this->hid();
        $vaccine = Vaccine::where('hospital_id', $hid)->findOrFail($id);
        $v = $this->validateVaccine($request);
        $v['is_active'] = (bool) $request->input('is_active', false);
        $vaccine->update($v);

        return back()->with('success', 'Vaccine updated.');
    }

    public function destroyVaccine(string $id)
    {
        $hid = $this->hid();
        Vaccine::where('hospital_id', $hid)->where('id', $id)->update(['is_active' => false]);

        return back()->with('success', 'Vaccine deactivated.');
    }

    private function validateVaccine(Request $request): array
    {
        return $request->validate([
            'name'               => 'required|string|max:120',
            'code'               => 'nullable|string|max:40',
            'category'           => 'required|in:' . implode(',', array_keys(Vaccine::CATEGORIES)),
            'route'              => 'required|in:' . implode(',', array_keys(Vaccine::ROUTES)),
            'total_doses'        => 'required|integer|min:1|max:20',
            'dose_interval_days' => 'nullable|integer|min:1|max:3650',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Modules\Billing\Models\Bill;
use App\Modules\Dental\Models\DentalChart;
use App\Modules\Dental\Models\DentalProcedure;
use App\Modules\Dental\Models\DentalTreatment;
use App\Modules\Dental\Models\DentalVisit;
use App\Modules\Core\Services\RegionService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DentalController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();

        // Fee schedule (procedure master) is always available for charting + the catalogue tab.
        $procedures = DentalProcedure::where('hospital_id', $hid)->orderBy('category')->orderBy('name')->get();

        // The dentist's own booked patients (today + upcoming) — so they can see
        // who's coming in and jump straight into the chart to consult. Scoped to
        // the logged-in practitioner's staff record.
        $appointments = collect();
        $user    = Auth::user();
        $staffId = $user->staff?->id;
        $role    = is_object($user->role) ? $user->role->value : $user->role;
        $isDentalPractitioner = $role === 'dentist' || ($user->staff?->department === 'Dental');
        if ($staffId) {
            $appointments = \App\Modules\Appointment\Models\Appointment::where('hospital_id', $hid)
                ->where('doctor_id', $staffId)
                ->whereDate('slot_start', '>=', today())
                ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
                ->with('patient')
                ->orderBy('slot_start')
                ->limit(50)
                ->get();
        }

        $patient = null;
        $chart = null;
        $treatments = collect();
        $visits = collect();
        $plan = ['planned' => 0.0, 'completed' => 0.0, 'unbilled' => 0.0, 'unbilled_count' => 0];

        if ($request->filled('patient')) {
            $patient = Patient::where('hospital_id', $hid)->find($request->query('patient'));
            if ($patient) {
                $chart = DentalChart::where('hospital_id', $hid)->where('patient_id', $patient->id)->first();
                $treatments = DentalTreatment::where('hospital_id', $hid)->where('patient_id', $patient->id)
                    ->latest('created_at')->get();
                $visits = DentalVisit::where('hospital_id', $hid)->where('patient_id', $patient->id)
                    ->orderByDesc('visit_date')->limit(50)->get();

                $plan['planned'] = (float) $treatments->whereIn('status', ['planned', 'in_progress'])->sum('cost');
                $plan['completed'] = (float) $treatments->where('status', 'completed')->sum('cost');
                $unbilled = $treatments->where('status', 'completed')->whereNull('bill_id');
                $plan['unbilled'] = (float) $unbilled->sum('cost');
                $plan['unbilled_count'] = $unbilled->count();
            }
        }

        return view('dental.index', compact('patient', 'chart', 'treatments', 'visits', 'procedures', 'plan', 'appointments', 'isDentalPractitioner'));
    }

    public function saveChart(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'   => 'required|uuid',
            'dentition'    => 'required|in:adult,pediatric',
            'tooth_status' => 'nullable|string',
            'notes'        => 'nullable|string|max:2000',
        ]);

        $status = $v['tooth_status'] ? (json_decode($v['tooth_status'], true) ?: []) : [];

        DentalChart::updateOrCreate(
            ['hospital_id' => $hid, 'patient_id' => $v['patient_id']],
            ['dentition' => $v['dentition'], 'tooth_status' => $status, 'notes' => $v['notes'] ?? null]
        );

        return redirect()->route('web.dental.index', ['patient' => $v['patient_id']])->with('success', 'Dental chart saved.');
    }

    public function addTreatment(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'   => 'required|uuid',
            'procedure_id' => 'nullable|uuid',
            'tooth_number' => 'nullable|string|max:10',
            'surfaces'     => 'nullable|string|max:12',
            'procedure'    => 'required|string|max:150',
            'status'       => 'required|in:' . implode(',', array_keys(DentalTreatment::STATUSES)),
            'cost'         => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        // If a fee-schedule procedure was chosen, trust its name/fee as the source of truth.
        $procedureId = null;
        $name = $v['procedure'];
        $cost = $v['cost'] ?? 0;
        if (! empty($v['procedure_id'])) {
            $proc = DentalProcedure::where('hospital_id', $hid)->find($v['procedure_id']);
            if ($proc) {
                $procedureId = $proc->id;
                $name = $proc->name;
                if (! $request->filled('cost')) {
                    $cost = $proc->default_fee;
                }
            }
        }

        DentalTreatment::create([
            'hospital_id'    => $hid,
            'patient_id'     => $v['patient_id'],
            'procedure_id'   => $procedureId,
            'tooth_number'   => $v['tooth_number'] ?? null,
            'surfaces'       => ! empty($v['surfaces']) ? strtoupper($v['surfaces']) : null,
            'procedure'      => $name,
            'status'         => $v['status'],
            'performed_date' => $v['status'] === 'completed' ? today() : null,
            'cost'           => $cost,
            'notes'          => $v['notes'] ?? null,
        ]);

        return redirect()->route('web.dental.index', ['patient' => $v['patient_id']])->with('success', 'Treatment added to the plan.');
    }

    public function updateTreatment(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate(['status' => 'required|in:' . implode(',', array_keys(DentalTreatment::STATUSES))]);
        $t = DentalTreatment::where('hospital_id', $hid)->findOrFail($id);

        $patch = ['status' => $v['status']];
        if ($v['status'] === 'completed' && ! $t->performed_date) {
            $patch['performed_date'] = today();
        }
        $t->update($patch);

        return redirect()->route('web.dental.index', ['patient' => $t->patient_id])->with('success', 'Treatment updated.');
    }

    public function deleteTreatment(string $id)
    {
        $hid = $this->hid();
        $t = DentalTreatment::where('hospital_id', $hid)->findOrFail($id);
        if ($t->bill_id) {
            return back()->with('error', 'This treatment is already billed and cannot be removed.');
        }
        $pid = $t->patient_id;
        $t->delete();

        return redirect()->route('web.dental.index', ['patient' => $pid])->with('success', 'Treatment removed.');
    }

    /** Turn completed, not-yet-billed treatments into a MedOS bill (flows to billing + external ERP). */
    public function billTreatments(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate(['patient_id' => 'required|uuid']);

        $patient = Patient::where('hospital_id', $hid)->findOrFail($v['patient_id']);
        $due = DentalTreatment::where('hospital_id', $hid)->where('patient_id', $patient->id)
            ->where('status', 'completed')->whereNull('bill_id')->get();

        if ($due->isEmpty()) {
            return back()->with('error', 'No completed, unbilled treatments to invoice.');
        }

        $items = $due->map(function (DentalTreatment $t) {
            $desc = $t->procedure;
            if ($t->tooth_number) {
                $desc .= ' — tooth ' . $t->tooth_number . ($t->surfaces ? ' (' . $t->surfaces . ')' : '');
            }
            return [
                'description' => $desc,
                'category'    => 'dental',
                'quantity'    => 1,
                'unit_price'  => round((float) $t->cost, 2),
                'taxable'     => false,
                'total'       => round((float) $t->cost, 2),
            ];
        })->values()->all();

        $total = round(collect($items)->sum('total'), 2);

        // Every bill needs an encounter — create a lightweight dental encounter.
        $encounterId = Encounter::create([
            'id'               => Str::uuid()->toString(),
            'hospital_id'      => $hid,
            'patient_id'       => $patient->id,
            'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'type'             => 'consultation',
            'status'           => 'completed',
            'channel'          => 'walk_in',
            'intake_data'      => ['source' => 'dental'],
        ])->id;

        $bill = Bill::create([
            'id'                => Str::uuid()->toString(),
            'hospital_id'       => $hid,
            'encounter_id'      => $encounterId,
            'patient_id'        => $patient->id,
            'bill_number'       => 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'bill_type'         => 'standalone',
            'line_items'        => $items,
            'subtotal'          => $total,
            'tax_rate'          => 0,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'insurance_covered' => 0,
            'total_amount'      => $total,
            'patient_payable'   => $total,
            'amount_paid'       => 0,
            'balance_due'       => $total,
            'payment_status'    => 'pending',
            'currency'          => RegionService::currencyCode(),
            'notes'             => 'Dental treatment — ' . $due->count() . ' procedure(s).',
            'issued_at'         => now(),
        ]);

        DentalTreatment::whereIn('id', $due->pluck('id'))->update(['bill_id' => $bill->id]);

        return redirect()->route('web.billing.show', $bill->id)
            ->with('success', 'Bill ' . $bill->bill_number . ' created from ' . $due->count() . ' dental procedure(s).');
    }

    public function addVisit(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'      => 'required|uuid',
            'visit_date'      => 'required|date',
            'chief_complaint' => 'nullable|string|max:255',
            'examination'     => 'nullable|string|max:2000',
            'procedures_done' => 'nullable|string|max:2000',
            'advice'          => 'nullable|string|max:2000',
            'next_visit_date' => 'nullable|date',
        ]);

        DentalVisit::create(array_merge($v, [
            'hospital_id'  => $hid,
            'dentist_name' => Auth::user()->name,
        ]));

        return redirect()->route('web.dental.index', ['patient' => $v['patient_id']])->with('success', 'Visit note recorded.');
    }

    // ---------------------------------------------------------------
    // Fee schedule (procedure master)
    // ---------------------------------------------------------------

    public function storeProcedure(Request $request)
    {
        $hid = $this->hid();
        $v = $this->validateProcedure($request);
        $v['hospital_id'] = $hid;
        $v['is_active'] = true;
        DentalProcedure::create($v);

        return redirect()->route('web.dental.index')->with('success', 'Procedure added to the fee schedule.');
    }

    public function updateProcedure(Request $request, string $id)
    {
        $hid = $this->hid();
        $proc = DentalProcedure::where('hospital_id', $hid)->findOrFail($id);
        $v = $this->validateProcedure($request);
        $v['is_active'] = (bool) $request->input('is_active', false);
        $proc->update($v);

        return redirect()->route('web.dental.index')->with('success', 'Fee schedule updated.');
    }

    private function validateProcedure(Request $request): array
    {
        return $request->validate([
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:150',
            'category'    => 'required|in:' . implode(',', array_keys(DentalProcedure::CATEGORIES)),
            'default_fee' => 'required|numeric|min:0',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Services\RegionService;
use App\Modules\Ophthalmology\Models\EyeExam;
use App\Modules\Ophthalmology\Models\EyeProcedure;
use App\Modules\Ophthalmology\Models\EyeTreatment;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ophthalmology (Eye Hospital) workspace — mirrors the Dental module:
 * per-patient exams (VA / IOP / refraction / spectacle Rx / segments / diagnosis),
 * a procedure fee schedule, a treatment plan, and one-click billing into MedOS.
 */
class EyeController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();

        $procedures = EyeProcedure::where('hospital_id', $hid)->orderBy('category')->orderBy('name')->get();

        // The ophthalmologist's own booked patients (today + upcoming).
        $appointments = collect();
        $user    = Auth::user();
        $staffId = $user->staff?->id;
        $role    = is_object($user->role) ? $user->role->value : $user->role;
        $isEyePractitioner = $user->staff?->department === 'Ophthalmology';
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
        $exams = collect();
        $treatments = collect();
        $lastExam = null;
        $plan = ['planned' => 0.0, 'completed' => 0.0, 'unbilled' => 0.0, 'unbilled_count' => 0];

        if ($request->filled('patient')) {
            $patient = Patient::where('hospital_id', $hid)->find($request->query('patient'));
            if ($patient) {
                $exams = EyeExam::where('hospital_id', $hid)->where('patient_id', $patient->id)
                    ->orderByDesc('exam_date')->orderByDesc('created_at')->limit(50)->get();
                $lastExam = $exams->first();
                $treatments = EyeTreatment::where('hospital_id', $hid)->where('patient_id', $patient->id)
                    ->latest('created_at')->get();

                $plan['planned'] = (float) $treatments->whereIn('status', ['planned', 'in_progress'])->sum('cost');
                $plan['completed'] = (float) $treatments->where('status', 'completed')->sum('cost');
                $unbilled = $treatments->where('status', 'completed')->whereNull('bill_id');
                $plan['unbilled'] = (float) $unbilled->sum('cost');
                $plan['unbilled_count'] = $unbilled->count();
            }
        }

        return view('eye.index', compact(
            'patient', 'exams', 'lastExam', 'treatments', 'procedures', 'plan', 'appointments', 'isEyePractitioner'
        ));
    }

    // ---------------------------------------------------------------
    // Eye exam (VA / IOP / refraction / segments / diagnosis)
    // ---------------------------------------------------------------
    public function saveExam(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'        => 'required|uuid',
            'exam_date'         => 'required|date',
            'chief_complaint'   => 'nullable|string|max:255',
            'va_od_unaided'     => 'nullable|string|max:12',
            'va_od_aided'       => 'nullable|string|max:12',
            'va_os_unaided'     => 'nullable|string|max:12',
            'va_os_aided'       => 'nullable|string|max:12',
            'iop_od'            => 'nullable|numeric|min:0|max:80',
            'iop_os'            => 'nullable|numeric|min:0|max:80',
            'od_sph'            => 'nullable|string|max:10',
            'od_cyl'            => 'nullable|string|max:10',
            'od_axis'           => 'nullable|string|max:10',
            'od_add'            => 'nullable|string|max:10',
            'os_sph'            => 'nullable|string|max:10',
            'os_cyl'            => 'nullable|string|max:10',
            'os_axis'           => 'nullable|string|max:10',
            'os_add'            => 'nullable|string|max:10',
            'pd'                => 'nullable|string|max:10',
            'rx_type'           => 'nullable|in:' . implode(',', array_keys(EyeExam::RX_TYPES)),
            'anterior_segment'  => 'nullable|string|max:2000',
            'posterior_segment' => 'nullable|string|max:2000',
            'diagnosis'         => 'nullable|string|max:2000',
            'advice'            => 'nullable|string|max:2000',
            'next_visit_date'   => 'nullable|date',
        ]);

        Patient::where('hospital_id', $hid)->findOrFail($v['patient_id']);

        EyeExam::create(array_merge($v, [
            'hospital_id'   => $hid,
            'examiner_name' => Auth::user()->name,
        ]));

        return redirect()->route('web.eye.index', ['patient' => $v['patient_id']])->with('success', 'Eye exam recorded.');
    }

    // ---------------------------------------------------------------
    // Treatment plan
    // ---------------------------------------------------------------
    public function addTreatment(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'   => 'required|uuid',
            'procedure_id' => 'nullable|uuid',
            'eye'          => 'nullable|in:' . implode(',', array_keys(EyeTreatment::EYES)),
            'procedure'    => 'required|string|max:150',
            'status'       => 'required|in:' . implode(',', array_keys(EyeTreatment::STATUSES)),
            'cost'         => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        Patient::where('hospital_id', $hid)->findOrFail($v['patient_id']);

        // If a fee-schedule procedure was chosen, trust its name/fee as source of truth.
        $procedureId = null;
        $name = $v['procedure'];
        $cost = $v['cost'] ?? 0;
        if (! empty($v['procedure_id'])) {
            $proc = EyeProcedure::where('hospital_id', $hid)->find($v['procedure_id']);
            if ($proc) {
                $procedureId = $proc->id;
                $name = $proc->name;
                if (! $request->filled('cost')) {
                    $cost = $proc->default_fee;
                }
            }
        }

        EyeTreatment::create([
            'hospital_id'    => $hid,
            'patient_id'     => $v['patient_id'],
            'procedure_id'   => $procedureId,
            'eye'            => $v['eye'] ?? null,
            'procedure'      => $name,
            'status'         => $v['status'],
            'performed_date' => $v['status'] === 'completed' ? today() : null,
            'cost'           => $cost,
            'notes'          => $v['notes'] ?? null,
        ]);

        return redirect()->route('web.eye.index', ['patient' => $v['patient_id']])->with('success', 'Procedure added to the plan.');
    }

    public function updateTreatment(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate(['status' => 'required|in:' . implode(',', array_keys(EyeTreatment::STATUSES))]);
        $t = EyeTreatment::where('hospital_id', $hid)->findOrFail($id);

        $patch = ['status' => $v['status']];
        if ($v['status'] === 'completed' && ! $t->performed_date) {
            $patch['performed_date'] = today();
        }
        $t->update($patch);

        return redirect()->route('web.eye.index', ['patient' => $t->patient_id])->with('success', 'Procedure updated.');
    }

    public function deleteTreatment(string $id)
    {
        $hid = $this->hid();
        $t = EyeTreatment::where('hospital_id', $hid)->findOrFail($id);
        if ($t->bill_id) {
            return back()->with('error', 'This procedure is already billed and cannot be removed.');
        }
        $pid = $t->patient_id;
        $t->delete();

        return redirect()->route('web.eye.index', ['patient' => $pid])->with('success', 'Procedure removed.');
    }

    /** Turn completed, not-yet-billed procedures into a MedOS bill. */
    public function billTreatments(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate(['patient_id' => 'required|uuid']);

        $patient = Patient::where('hospital_id', $hid)->findOrFail($v['patient_id']);
        $due = EyeTreatment::where('hospital_id', $hid)->where('patient_id', $patient->id)
            ->where('status', 'completed')->whereNull('bill_id')->get();

        if ($due->isEmpty()) {
            return back()->with('error', 'No completed, unbilled procedures to invoice.');
        }

        $items = $due->map(function (EyeTreatment $t) {
            $desc = $t->procedure;
            if ($t->eye) {
                $desc .= ' — ' . (EyeTreatment::EYES[$t->eye] ?? strtoupper($t->eye));
            }
            return [
                'description' => $desc,
                'category'    => 'ophthalmology',
                'quantity'    => 1,
                'unit_price'  => round((float) $t->cost, 2),
                'taxable'     => false,
                'total'       => round((float) $t->cost, 2),
            ];
        })->values()->all();

        $total = round(collect($items)->sum('total'), 2);

        $encounterId = Encounter::create([
            'id'               => Str::uuid()->toString(),
            'hospital_id'      => $hid,
            'patient_id'       => $patient->id,
            'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'type'             => 'consultation',
            'status'           => 'completed',
            'channel'          => 'walk_in',
            'intake_data'      => ['source' => 'ophthalmology'],
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
            'notes'             => 'Ophthalmology — ' . $due->count() . ' procedure(s).',
            'issued_at'         => now(),
        ]);

        EyeTreatment::whereIn('id', $due->pluck('id'))->update(['bill_id' => $bill->id]);

        return redirect()->route('web.billing.show', $bill->id)
            ->with('success', 'Bill ' . $bill->bill_number . ' created from ' . $due->count() . ' eye procedure(s).');
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
        EyeProcedure::create($v);

        return redirect()->route('web.eye.index')->with('success', 'Procedure added to the fee schedule.');
    }

    public function updateProcedure(Request $request, string $id)
    {
        $hid = $this->hid();
        $proc = EyeProcedure::where('hospital_id', $hid)->findOrFail($id);
        $v = $this->validateProcedure($request);
        $v['is_active'] = (bool) $request->input('is_active', false);
        $proc->update($v);

        return redirect()->route('web.eye.index')->with('success', 'Fee schedule updated.');
    }

    private function validateProcedure(Request $request): array
    {
        return $request->validate([
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:150',
            'category'    => 'required|in:' . implode(',', array_keys(EyeProcedure::CATEGORIES)),
            'default_fee' => 'required|numeric|min:0',
        ]);
    }
}

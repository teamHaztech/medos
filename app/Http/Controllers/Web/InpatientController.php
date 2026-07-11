<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Models\ChargeItem;
use App\Modules\Billing\Models\ServiceCharge;
use App\Modules\Billing\Services\ChargeCapture;
use App\Modules\Core\Models\Staff;
use App\Modules\Inpatient\Models\Admission;
use App\Modules\Inpatient\Models\Bed;
use App\Modules\Inpatient\Models\IpIntakeOutput;
use App\Modules\Inpatient\Models\IpNote;
use App\Modules\Inpatient\Models\IpVital;
use App\Modules\Inpatient\Models\Ward;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class InpatientController extends Controller
{
    private function hid(): string
    {
        $hid = Auth::user()->hospital_id;
        config(['medos.current_hospital_id' => $hid]);

        return $hid;
    }

    private function ready(): bool
    {
        return Schema::hasTable('admissions') && Schema::hasTable('wards') && Schema::hasTable('beds');
    }

    // ---------------------------------------------------------------
    // Bed board / census dashboard
    // ---------------------------------------------------------------

    public function dashboard()
    {
        $this->hid();

        if (! $this->ready()) {
            return view('ip.dashboard', ['wards' => collect(), 'stats' => $this->emptyStats()]);
        }

        $wards = Ward::where('is_active', true)
            ->with(['beds' => fn ($q) => $q->where('is_active', true), 'beds.admission.patient'])
            ->orderBy('name')
            ->get();

        $totalBeds = $wards->sum(fn ($w) => $w->beds->count());
        $occupied  = $wards->sum(fn ($w) => $w->occupiedCount());

        $discharged = Admission::where('status', 'discharged')->whereNotNull('discharged_at')->get();
        $avgLos = $discharged->count() ? round($discharged->avg(fn ($a) => $a->lengthOfDays()), 1) : 0;

        $stats = [
            'inpatients'    => Admission::where('status', 'admitted')->count(),
            'total_beds'    => $totalBeds,
            'available'     => max(0, $totalBeds - $occupied),
            'occupancy_pct' => $totalBeds ? (int) round(($occupied / $totalBeds) * 100) : 0,
            'avg_los'       => $avgLos,
        ];

        return view('ip.dashboard', compact('wards', 'stats'));
    }

    private function emptyStats(): array
    {
        return ['inpatients' => 0, 'total_beds' => 0, 'available' => 0, 'occupancy_pct' => 0, 'avg_los' => 0];
    }

    // ---------------------------------------------------------------
    // Admissions list + admit
    // ---------------------------------------------------------------

    public function admissions(Request $request)
    {
        $hid = $this->hid();

        if (! $this->ready()) {
            return view('ip.admissions', ['admissions' => collect(), 'patients' => collect(), 'wards' => collect(), 'doctors' => collect(), 'showDischarged' => false]);
        }

        $showDischarged = $request->boolean('discharged');
        $admissions = Admission::where('status', $showDischarged ? 'discharged' : 'admitted')
            ->with(['patient', 'ward', 'bed', 'attendingDoctor'])
            ->orderByDesc('admitted_at')
            ->get();

        $patients = Patient::where('hospital_id', $hid)->orderBy('name')->get(['id', 'name', 'phone']);
        $wards = Ward::where('is_active', true)->with(['beds' => fn ($q) => $q->where('status', 'available')->where('is_active', true)])->orderBy('name')->get();
        $doctors = Staff::where('hospital_id', $hid)->where('is_active', true)->whereIn('role', ['doctor', 'hospital_admin'])->orderBy('name')->get(['id', 'name']);

        return view('ip.admissions', compact('admissions', 'patients', 'wards', 'doctors', 'showDischarged'));
    }

    public function admit(Request $request)
    {
        $hid = $this->hid();

        $v = $request->validate([
            'patient_id'            => 'required|uuid|exists:patients,id',
            'ward_id'               => 'required|uuid|exists:wards,id',
            'bed_id'                => 'required|uuid|exists:beds,id',
            'admitting_doctor_id'   => 'nullable|uuid|exists:staff,id',
            'attending_doctor_id'   => 'nullable|uuid|exists:staff,id',
            'reason'                => 'nullable|string|max:255',
            'provisional_diagnosis' => 'nullable|string|max:255',
        ]);

        $bed = Bed::findOrFail($v['bed_id']);
        if ($bed->status !== 'available') {
            return redirect()->back()->with('error', 'That bed is no longer available. Please pick another.');
        }
        // Patient must not already have an active admission.
        if (Admission::where('patient_id', $v['patient_id'])->where('status', 'admitted')->exists()) {
            return redirect()->back()->with('error', 'This patient is already admitted.');
        }

        $seq = Admission::where('hospital_id', $hid)->whereDate('admitted_at', today())->count() + 1;

        // Snapshot the ward tariff onto the admission so later rate changes don't
        // retroactively alter this stay's bill.
        $ward = Ward::find($v['ward_id']);

        $admission = Admission::create([
            'hospital_id'           => $hid,
            'patient_id'            => $v['patient_id'],
            'admitting_doctor_id'   => $v['admitting_doctor_id'] ?? null,
            'attending_doctor_id'   => $v['attending_doctor_id'] ?? $v['admitting_doctor_id'] ?? null,
            'ward_id'               => $v['ward_id'],
            'bed_id'                => $bed->id,
            'room_daily_rate'       => $ward?->daily_rate ?? 0,
            'nursing_daily_rate'    => $ward?->nursing_daily_rate ?? 0,
            'bed_category'          => $ward?->ward_type,
            'admission_no'          => 'IP-' . now()->format('Ymd') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'admitted_at'           => now(),
            'reason'                => $v['reason'] ?? null,
            'provisional_diagnosis' => $v['provisional_diagnosis'] ?? null,
            'status'                => 'admitted',
        ]);

        $bed->update(['status' => 'occupied']);

        return redirect()->route('web.ip.show', $admission->id)->with('success', 'Patient admitted — ' . $admission->admission_no);
    }

    // ---------------------------------------------------------------
    // IP case sheet
    // ---------------------------------------------------------------

    public function show(string $id)
    {
        $this->hid();
        abort_unless($this->ready(), 404);

        $admission = Admission::with(['patient', 'ward', 'bed', 'admittingDoctor', 'attendingDoctor', 'vitals', 'intakeOutputs', 'notes'])
            ->findOrFail($id);

        // Beds available for transfer (plus the patient's current bed).
        $wards = Ward::where('is_active', true)
            ->with(['beds' => fn ($q) => $q->where('is_active', true)->where('status', 'available')])
            ->orderBy('name')->get();

        // Keep room/nursing accrual current, then load the running bill picture.
        try {
            app(ChargeCapture::class)->accrueRoom($admission, Auth::user()->name);
        } catch (\Throwable $e) {
            \Log::warning('[IPD] room accrual failed: ' . $e->getMessage());
        }
        $charges = ChargeItem::where('admission_id', $admission->id)
            ->where('status', '!=', ChargeItem::STATUS_CANCELLED)
            ->orderBy('posted_at')->get();
        $runningTotal = round((float) $charges->sum('total'), 2);
        $ipBill = Bill::withoutGlobalScopes()->where('admission_id', $admission->id)->first();
        $services = ServiceCharge::where('hospital_id', $admission->hospital_id)->where('is_active', true)
            ->orderBy('category')->orderBy('name')->get();

        return view('ip.show', compact('admission', 'wards', 'charges', 'runningTotal', 'ipBill', 'services'));
    }

    /** Post an ad-hoc charge (procedure / consumable / investigation) to the admission. */
    public function addCharge(Request $request, string $id)
    {
        $hid = $this->hid();
        $admission = Admission::findOrFail($id);

        $v = $request->validate([
            'service_charge_id' => 'nullable|uuid',
            'source'            => 'required|in:procedure,consumable,lab,imaging,nursing,other',
            'description'       => 'required|string|max:255',
            'unit_price'        => 'required|numeric|min:0',
            'quantity'          => 'required|numeric|min:0.01|max:9999',
        ]);

        $cc = app(ChargeCapture::class);
        $cc->post([
            'hospital_id'       => $hid,
            'patient_id'        => $admission->patient_id,
            'admission_id'      => $admission->id,
            'service_charge_id' => $v['service_charge_id'] ?? null,
            'source'            => $v['source'],
            'description'       => $v['description'],
            'unit_price'        => (float) $v['unit_price'],
            'quantity'          => (float) $v['quantity'],
            'posted_by_name'    => Auth::user()->name,
        ]);
        $cc->compileBill($admission, Auth::user()->name);

        return redirect()->route('web.ip.show', $admission->id)->with('success', 'Charge added to the inpatient bill.');
    }

    /** Compile (or refresh) the inpatient bill for an admission. */
    public function compileBill(string $id)
    {
        $this->hid();
        $admission = Admission::findOrFail($id);
        $bill = app(ChargeCapture::class)->compileBill($admission, Auth::user()->name);
        if (! $bill) {
            return back()->with('error', 'No charges to bill yet for this admission.');
        }

        return redirect()->route('web.billing.show', $bill->id)->with('success', 'Inpatient bill ' . $bill->bill_number . ' ready.');
    }

    public function storeVital(Request $request, string $id)
    {
        $hid = $this->hid();
        $admission = Admission::findOrFail($id);

        $v = $request->validate([
            'bp_systolic'  => 'nullable|integer|min:40|max:300',
            'bp_diastolic' => 'nullable|integer|min:20|max:200',
            'pulse'        => 'nullable|integer|min:20|max:250',
            'spo2'         => 'nullable|integer|min:50|max:100',
            'resp_rate'    => 'nullable|integer|min:5|max:80',
            'temperature'  => 'nullable|numeric|min:90|max:110',
            'weight'       => 'nullable|numeric|min:0|max:400',
            'height'       => 'nullable|numeric|min:0|max:260',
            'notes'        => 'nullable|string|max:255',
        ]);

        $bmi = null;
        if (! empty($v['weight']) && ! empty($v['height']) && $v['height'] > 0) {
            $m = $v['height'] / 100;
            $bmi = round($v['weight'] / ($m * $m), 1);
        }

        IpVital::create(array_merge($v, [
            'hospital_id' => $hid,
            'admission_id' => $admission->id,
            'recorded_by' => Auth::user()->name ?? 'Staff',
            'recorded_at' => now(),
            'bmi'         => $bmi,
        ]));

        return redirect()->route('web.ip.show', $admission->id)->with('success', 'Vitals recorded.');
    }

    public function storeIntakeOutput(Request $request, string $id)
    {
        $hid = $this->hid();
        $admission = Admission::findOrFail($id);

        $v = $request->validate([
            'direction' => 'required|in:intake,output',
            'category'  => 'nullable|string|max:50',
            'volume_ml' => 'required|integer|min:1|max:10000',
            'notes'     => 'nullable|string|max:255',
        ]);

        IpIntakeOutput::create([
            'hospital_id'  => $hid,
            'admission_id' => $admission->id,
            'recorded_at'  => now(),
            'direction'    => $v['direction'],
            'category'     => $v['category'] ?? null,
            'volume_ml'    => $v['volume_ml'],
            'notes'        => $v['notes'] ?? null,
            'recorded_by'  => Auth::user()->name ?? 'Staff',
        ]);

        return redirect()->route('web.ip.show', $admission->id)->with('success', 'Intake/output recorded.');
    }

    public function storeNote(Request $request, string $id)
    {
        $hid = $this->hid();
        $admission = Admission::findOrFail($id);

        $v = $request->validate([
            'note_type' => 'required|in:doctor,nurse,order',
            'body'      => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        IpNote::create([
            'hospital_id'  => $hid,
            'admission_id' => $admission->id,
            'author_id'    => $user->id ?? null,
            'author_name'  => $user->name ?? 'Staff',
            'author_role'  => is_object($user->role ?? null) ? $user->role->value : ($user->role ?? null),
            'note_type'    => $v['note_type'],
            'body'         => $v['body'],
        ]);

        return redirect()->route('web.ip.show', $admission->id)->with('success', 'Note added.');
    }

    // ---------------------------------------------------------------
    // Transfer + discharge
    // ---------------------------------------------------------------

    public function transfer(Request $request, string $id)
    {
        $hid = $this->hid();
        $admission = Admission::with('bed')->findOrFail($id);
        abort_unless($admission->isActive(), 400);

        $v = $request->validate([
            'bed_id' => 'required|uuid|exists:beds,id',
        ]);

        $newBed = Bed::findOrFail($v['bed_id']);
        if ($newBed->status !== 'available') {
            return redirect()->back()->with('error', 'That bed is no longer available.');
        }

        $oldBed = $admission->bed;
        if ($oldBed && $oldBed->id !== $newBed->id) {
            $oldBed->update(['status' => 'available']);
        }
        $newBed->update(['status' => 'occupied']);
        $admission->update(['ward_id' => $newBed->ward_id, 'bed_id' => $newBed->id]);

        IpNote::create([
            'hospital_id' => $hid, 'admission_id' => $admission->id,
            'author_name' => Auth::user()->name ?? 'Staff', 'note_type' => 'order',
            'body' => 'Transferred to bed ' . $newBed->bed_number . '.',
        ]);

        return redirect()->route('web.ip.show', $admission->id)->with('success', 'Patient transferred to ' . $newBed->bed_number . '.');
    }

    public function discharge(Request $request, string $id)
    {
        $this->hid();
        $admission = Admission::with('bed')->findOrFail($id);
        abort_unless($admission->isActive(), 400);

        $v = $request->validate([
            'discharge_type'    => 'required|in:recovered,referred,dama,lama,expired',
            'discharge_summary' => 'nullable|string|max:5000',
        ]);

        $admission->update([
            'status'            => 'discharged',
            'discharged_at'     => now(),
            'discharge_type'    => $v['discharge_type'],
            'discharge_summary' => $v['discharge_summary'] ?? null,
        ]);

        if ($admission->bed) {
            $admission->bed->update(['status' => 'available']);
        }

        // Compile the final inpatient bill now that the stay (and its room days) is
        // fixed. Non-fatal so a billing hiccup never blocks the discharge.
        $bill = null;
        try {
            $bill = app(ChargeCapture::class)->compileBill($admission->fresh(), Auth::user()->name);
        } catch (\Throwable $e) {
            \Log::warning('[IPD] final bill compile failed: ' . $e->getMessage());
        }

        $msg = 'Patient discharged (' . $admission->admission_no . ').';
        if ($bill) {
            $msg .= ' Final bill ' . $bill->bill_number . ' — ' . \App\Modules\Core\Services\RegionService::currency() . number_format((float) $bill->total_amount, 2) . '.';
        }

        return redirect()->route('web.ip.admissions')->with('success', $msg);
    }

    // ---------------------------------------------------------------
    // Admission / Discharge (ADT) workflow tracking
    // ---------------------------------------------------------------

    /** ADT tracking board — active admissions by discharge stage + discharged today. */
    public function adt()
    {
        $this->hid();

        if (! $this->ready()) {
            return view('ip.adt', ['groups' => ['in_care' => collect(), 'initiated' => collect(), 'ready' => collect()], 'dischargedToday' => collect()]);
        }

        $active = Admission::with(['patient', 'ward', 'bed', 'attendingDoctor'])
            ->where('status', 'admitted')
            ->orderBy('admitted_at')
            ->get();

        $groups = [
            'in_care'   => $active->filter(fn ($a) => $a->dischargeStage() === 'in_care')->values(),
            'initiated' => $active->filter(fn ($a) => $a->dischargeStage() === 'initiated')->values(),
            'ready'     => $active->filter(fn ($a) => $a->dischargeStage() === 'ready')->values(),
        ];

        $dischargedToday = Admission::with(['patient', 'ward'])
            ->where('status', 'discharged')
            ->whereDate('discharged_at', today())
            ->orderByDesc('discharged_at')
            ->get();

        return view('ip.adt', compact('groups', 'dischargedToday'));
    }

    /** Start the discharge workflow for an admission. */
    public function initiateDischarge(string $id)
    {
        $this->hid();
        $admission = Admission::findOrFail($id);
        abort_unless($admission->isActive(), 400);

        if ($admission->discharge_status !== 'initiated') {
            $admission->update([
                'discharge_status'       => 'initiated',
                'discharge_initiated_at' => now(),
            ]);
        }

        return back()->with('success', 'Discharge initiated for ' . $admission->admission_no . ' — complete the clearances.');
    }

    /** Toggle a discharge clearance step (billing | pharmacy | nursing). */
    public function toggleClearance(Request $request, string $id)
    {
        $this->hid();
        $admission = Admission::findOrFail($id);
        abort_unless($admission->isActive(), 400);

        $v = $request->validate(['type' => 'required|in:billing,pharmacy,nursing']);
        $col = $v['type'] . '_cleared_at';

        $updates = [$col => $admission->{$col} ? null : now()];
        // Toggling a clearance implicitly starts the discharge workflow.
        if ($admission->discharge_status !== 'initiated') {
            $updates['discharge_status'] = 'initiated';
            $updates['discharge_initiated_at'] = now();
        }
        $admission->update($updates);

        $fresh = $admission->fresh();
        return response()->json([
            'success'      => true,
            'cleared'      => ! is_null($fresh->{$col}),
            'ready'        => $fresh->readyToDischarge(),
            'cleared_count'=> $fresh->clearedCount(),
        ]);
    }
}

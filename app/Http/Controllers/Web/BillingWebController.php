<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Models\BillPayment;
use App\Modules\Billing\Models\ChargeItem;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\ChargeCapture;
use App\Modules\Core\Models\Order;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Services\RegionService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BillingWebController extends Controller
{
    /**
     * List bills for the hospital with filters.
     */
    public function index(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;

        $query = Bill::where('hospital_id', $hospitalId)
            ->with(['patient', 'encounter']);

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by patient name or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $bills = $query->orderByDesc('created_at')->paginate(20);

        // Completed encounters that have not been billed yet — these are the
        // patients who went through consultation/lab but have no bill.
        $billedEncounterIds = Bill::where('hospital_id', $hospitalId)->pluck('encounter_id');
        $unbilled = Encounter::where('hospital_id', $hospitalId)
            ->where('status', 'completed')
            ->whereNotIn('id', $billedEncounterIds)
            ->with(['patient', 'doctor'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('billing.index', compact('bills', 'unbilled'));
    }

    /**
     * Show bill detail.
     */
    public function show(string $id)
    {
        $bill = Bill::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'encounter.doctor', 'payments', 'insuranceClaim'])
            ->findOrFail($id);

        // Charge-capture ledger for this bill's encounter (pending + billed), so staff
        // can see every captured charge and whether the bill is in sync with it.
        $charges = collect();
        if ($bill->encounter_id) {
            $charges = ChargeItem::where('hospital_id', $bill->hospital_id)
                ->where('encounter_id', $bill->encounter_id)
                ->where('status', '!=', ChargeItem::STATUS_CANCELLED)
                ->orderBy('posted_at')->get();
        }
        $pendingCharges = $charges->where('status', ChargeItem::STATUS_PENDING);

        return view('billing.show', compact('bill', 'charges', 'pendingCharges'));
    }

    /**
     * Compile an encounter's captured charges into its bill (create or refresh),
     * marking the charges billed. This is the ledger → invoice step.
     */
    public function compileCharges(string $encounterId, ChargeCapture $charges)
    {
        $hospitalId = Auth::user()->hospital_id;
        $encounter = Encounter::where('hospital_id', $hospitalId)->findOrFail($encounterId);

        $bill = $charges->compileBill($encounter, Auth::user()->name);
        if (! $bill) {
            return back()->with('error', 'No captured charges to bill for this encounter.');
        }

        return redirect()->route('web.billing.show', $bill->id)
            ->with('success', 'Bill ' . $bill->bill_number . ' compiled from captured charges.');
    }

    /**
     * Create bill form — pre-populate from encounter orders.
     */
    public function create(string $encounterId, BillingService $billingService)
    {
        $encounter = Encounter::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'doctor'])
            ->findOrFail($encounterId);

        // A bill already exists for this encounter — show it instead of creating a duplicate.
        $existing = Bill::where('encounter_id', $encounter->id)->first();
        if ($existing) {
            return redirect()->route('web.billing.show', $existing->id)
                ->with('info', 'A bill already exists for this encounter.');
        }

        $data = $billingService->buildBillData($encounter);

        $lineItems = $data['line_items'];
        $subtotal  = $data['subtotal'];
        $taxRate   = $data['tax_rate'];
        $taxAmount = $data['tax_amount'];

        return view('billing.create', compact('encounter', 'lineItems', 'subtotal', 'taxRate', 'taxAmount'));
    }

    /**
     * Store a new bill.
     */
    public function store(Request $request)
    {
        $request->validate([
            'encounter_id'     => 'required|exists:encounters,id',
            'line_items'       => 'required|array',
            'subtotal'         => 'required|numeric|min:0',
            'tax_amount'       => 'required|numeric|min:0',
            'discount_amount'  => 'required|numeric|min:0',
            'insurance_covered'=> 'required|numeric|min:0',
        ]);

        $encounter = Encounter::with('patient')
            ->where('hospital_id', Auth::user()->hospital_id)
            ->findOrFail($request->encounter_id);

        // Never create a second bill for the same encounter.
        $existing = Bill::where('encounter_id', $encounter->id)->first();
        if ($existing) {
            return redirect()->route('web.billing.show', $existing->id)
                ->with('info', 'A bill already exists for this encounter.');
        }

        $totalAmount = $request->subtotal + $request->tax_amount - $request->discount_amount;
        $patientPayable = $totalAmount - $request->insurance_covered;

        $bill = Bill::create([
            'id'                => Str::uuid()->toString(),
            'hospital_id'       => Auth::user()->hospital_id,
            'encounter_id'      => $encounter->id,
            'patient_id'        => $encounter->patient_id,
            'bill_number'       => 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'bill_type'         => 'encounter',
            'line_items'        => $request->line_items,
            'subtotal'          => $request->subtotal,
            'tax_amount'        => $request->tax_amount,
            'discount_amount'   => $request->discount_amount,
            'insurance_covered' => $request->insurance_covered,
            'total_amount'      => $totalAmount,
            'patient_payable'   => $patientPayable,
            'amount_paid'       => 0,
            'balance_due'       => $patientPayable,
            'payment_status'    => 'pending',
            'currency'          => RegionService::currencyCode(),
            'issued_at'         => now(),
        ]);

        return redirect()->route('web.billing.show', $bill->id)
            ->with('success', 'Bill generated successfully.');
    }

    /**
     * Record a payment against a bill — adds a ledger entry and recomputes the
     * balance/status (supports partial payments and multiple instalments).
     */
    public function recordPayment(Request $request, string $id)
    {
        $v = $request->validate([
            'method'       => 'required|in:cash,upi,card,insurance,bank,cheque,deposit',
            'reference'    => 'nullable|string|max:255',
            'amount_paid'  => 'required|numeric|min:0.01|max:10000000',
            'paid_at'      => 'nullable|date',
            'notes'        => 'nullable|string|max:255',
        ]);

        $hospitalId = Auth::user()->hospital_id;
        $bill = Bill::where('hospital_id', $hospitalId)->findOrFail($id);

        if ($bill->isCancelled()) {
            return back()->with('error', 'This bill is cancelled.');
        }

        // Paying from the patient's advance deposit — verify balance and debit the ledger.
        if ($v['method'] === 'deposit') {
            $balance = \App\Modules\Billing\Models\PatientDeposit::balanceFor($hospitalId, $bill->patient_id);
            if ($balance + 0.009 < (float) $v['amount_paid']) {
                return back()->with('error', 'Insufficient deposit balance (' . RegionService::currency() . number_format($balance, 2) . ' available).');
            }
            \App\Modules\Billing\Models\PatientDeposit::create([
                'hospital_id'      => $hospitalId,
                'patient_id'       => $bill->patient_id,
                'amount'           => -1 * (float) $v['amount_paid'],
                'type'             => 'adjust',
                'method'           => 'deposit',
                'reference'        => $bill->id,
                'received_by_name' => Auth::user()->name ?? 'Staff',
                'notes'            => 'Applied to bill ' . $bill->bill_number,
            ]);
            $bill->update(['deposit_applied' => round((float) $bill->deposit_applied + (float) $v['amount_paid'], 2)]);
        }

        BillPayment::create([
            'hospital_id'      => $bill->hospital_id,
            'bill_id'          => $bill->id,
            'amount'           => $v['amount_paid'],
            'method'           => $v['method'],
            'reference'        => $v['reference'] ?? null,
            'received_by'      => Auth::id(),
            'received_by_name' => Auth::user()->name ?? 'Staff',
            'paid_at'          => $v['paid_at'] ?? now(),
            'notes'            => $v['notes'] ?? null,
        ]);

        $this->recomputeBill($bill);

        return redirect()->route('web.billing.show', $bill->id)->with('success', 'Payment of ' . RegionService::currency() . number_format((float) $v['amount_paid'], 2) . ' recorded.');
    }

    /**
     * Edit a recorded payment (billing staff correction).
     */
    public function updatePayment(Request $request, string $billId, string $paymentId)
    {
        $hospitalId = Auth::user()->hospital_id;
        $bill = Bill::where('hospital_id', $hospitalId)->findOrFail($billId);
        $payment = BillPayment::where('hospital_id', $hospitalId)->where('bill_id', $bill->id)->findOrFail($paymentId);

        $v = $request->validate([
            'method'      => 'required|in:cash,upi,card,insurance,bank,cheque',
            'reference'   => 'nullable|string|max:255',
            'amount_paid' => 'required|numeric|min:0.01|max:10000000',
            'paid_at'     => 'nullable|date',
        ]);

        $payment->update([
            'amount'    => $v['amount_paid'],
            'method'    => $v['method'],
            'reference' => $v['reference'] ?? null,
            'paid_at'   => $v['paid_at'] ?? $payment->paid_at,
        ]);

        $this->recomputeBill($bill);

        return redirect()->route('web.billing.show', $bill->id)->with('success', 'Payment updated.');
    }

    /**
     * Delete a recorded payment.
     */
    public function deletePayment(string $billId, string $paymentId)
    {
        $hospitalId = Auth::user()->hospital_id;
        $bill = Bill::where('hospital_id', $hospitalId)->findOrFail($billId);
        $payment = BillPayment::where('hospital_id', $hospitalId)->where('bill_id', $bill->id)->findOrFail($paymentId);
        $payment->delete();

        $this->recomputeBill($bill);

        return redirect()->route('web.billing.show', $bill->id)->with('success', 'Payment removed.');
    }

    /**
     * Recompute a bill's amount_paid / balance_due / status from its ledger.
     */
    private function recomputeBill(Bill $bill): void
    {
        $paid     = round((float) $bill->payments()->sum('amount'), 2); // net of refunds
        $refunded = round((float) abs($bill->payments()->where('amount', '<', 0)->sum('amount')), 2);
        $payable  = round((float) $bill->patient_payable, 2);
        $balance  = max(0, round($payable - $paid, 2));
        $last     = $bill->payments()->where('amount', '>', 0)->orderByDesc('paid_at')->first();

        $wasWaived = ($bill->payment_status instanceof \App\Modules\Core\Enums\PaymentStatus
            ? $bill->payment_status->value : $bill->payment_status) === 'waived';

        if ($bill->isCancelled()) {
            $status = 'cancelled';
        } elseif ($refunded > 0 && $paid <= 0.009) {
            $status = 'refunded';
        } elseif ($paid > 0 && $balance <= 0.009) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        } elseif ($wasWaived) {
            $status = 'waived';
        } else {
            $status = 'pending';
        }

        $bill->update([
            'amount_paid'       => max(0, $paid),
            'balance_due'       => $balance,
            'payment_status'    => $status,
            'payment_method'    => $last?->method,
            'payment_reference' => $last?->reference,
            'paid_at'           => $status === 'paid' ? ($last?->paid_at ?? now()) : null,
        ]);
    }

    // ---------------------------------------------------------------
    // Service / charge master
    // ---------------------------------------------------------------

    public function services(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $query = \App\Modules\Billing\Models\ServiceCharge::where('hospital_id', $hospitalId);
        if ($cat = $request->get('category')) {
            $query->where('category', $cat);
        }
        if ($s = trim((string) $request->get('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        $services = $query->orderBy('category')->orderBy('name')->get();

        return view('billing.services', compact('services'));
    }

    public function storeService(Request $request)
    {
        $v = $request->validate($this->serviceRules());
        \App\Modules\Billing\Models\ServiceCharge::create(array_merge(
            ['id' => Str::uuid()->toString(), 'hospital_id' => Auth::user()->hospital_id],
            $this->serviceAttrs($v)
        ));

        return back()->with('success', 'Service added.');
    }

    public function updateService(Request $request, string $id)
    {
        $svc = \App\Modules\Billing\Models\ServiceCharge::where('hospital_id', Auth::user()->hospital_id)->findOrFail($id);
        $svc->update($this->serviceAttrs($request->validate($this->serviceRules())));

        return back()->with('success', 'Service updated.');
    }

    public function destroyService(string $id)
    {
        \App\Modules\Billing\Models\ServiceCharge::where('hospital_id', Auth::user()->hospital_id)->where('id', $id)->delete();

        return back()->with('success', 'Service removed.');
    }

    private function serviceRules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'code'       => 'nullable|string|max:50',
            'category'   => 'required|string|max:40',
            'price'      => 'required|numeric|min:0',
            'is_taxable' => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
        ];
    }

    private function serviceAttrs(array $v): array
    {
        return [
            'name'       => $v['name'],
            'code'       => $v['code'] ?? null,
            'category'   => $v['category'],
            'price'      => $v['price'],
            'is_taxable' => (bool) ($v['is_taxable'] ?? false),
            'is_active'  => (bool) ($v['is_active'] ?? true),
        ];
    }

    // ---------------------------------------------------------------
    // Itemized bill builder (standalone or from an encounter)
    // ---------------------------------------------------------------

    public function newBill(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $services = \App\Modules\Billing\Models\ServiceCharge::where('hospital_id', $hospitalId)
            ->where('is_active', true)->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'code', 'category', 'price', 'is_taxable']);

        $taxRate = (float) (\App\Modules\Core\Models\Hospital::find($hospitalId)?->config['tax_rate'] ?? 0);

        $prefillPatient = null;
        $encounterId = null;
        if ($eid = $request->get('encounter')) {
            $enc = Encounter::where('hospital_id', $hospitalId)->with('patient')->find($eid);
            if ($enc) {
                $encounterId = $enc->id;
                $prefillPatient = $enc->patient;
            }
        } elseif ($pid = $request->get('patient')) {
            $prefillPatient = \App\Modules\Patient\Models\Patient::where('hospital_id', $hospitalId)->find($pid);
        }

        $depositBalance = $prefillPatient
            ? \App\Modules\Billing\Models\PatientDeposit::balanceFor($hospitalId, $prefillPatient->id)
            : 0;

        return view('billing.new', compact('services', 'taxRate', 'prefillPatient', 'encounterId', 'depositBalance'));
    }

    public function storeBill(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $v = $request->validate([
            'patient_id'          => 'required|uuid|exists:patients,id',
            'encounter_id'        => 'nullable|uuid|exists:encounters,id',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.taxable'     => 'nullable|boolean',
            'items.*.category'    => 'nullable|string|max:40',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
            'discount_amount'     => 'nullable|numeric|min:0',
            'discount_reason'     => 'nullable|string|max:255',
            'insurance_covered'   => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $items = collect($v['items'])->map(function ($i) {
            $qty = (float) $i['quantity'];
            $up  = (float) $i['unit_price'];
            return [
                'description' => $i['description'],
                'category'    => $i['category'] ?? null,
                'quantity'    => $qty,
                'unit_price'  => $up,
                'taxable'     => (bool) ($i['taxable'] ?? false),
                'total'       => round($qty * $up, 2),
            ];
        })->values()->all();

        $subtotal    = round(collect($items)->sum('total'), 2);
        $taxableBase = round(collect($items)->where('taxable', true)->sum('total'), 2);
        $taxRate     = round((float) ($v['tax_rate'] ?? 0), 2);
        $taxAmount   = round($taxableBase * $taxRate / 100, 2);
        $discount    = round((float) ($v['discount_amount'] ?? 0), 2);
        $insurance   = round((float) ($v['insurance_covered'] ?? 0), 2);
        $total       = round($subtotal + $taxAmount - $discount, 2);
        $payable     = max(0, round($total - $insurance, 2));

        $encounterId = $v['encounter_id'] ?? null;
        $billType = $encounterId ? 'encounter' : 'standalone';
        if ($encounterId) {
            if (Bill::where('encounter_id', $encounterId)->exists()) {
                return back()->withInput()->with('error', 'A bill already exists for that encounter.');
            }
        } else {
            // Standalone bill — bills.encounter_id is required, so create a lightweight billing encounter.
            $encounterId = Encounter::create([
                'id'               => Str::uuid()->toString(),
                'hospital_id'      => $hospitalId,
                'patient_id'       => $v['patient_id'],
                'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                'type'             => 'consultation',
                'status'           => 'completed',
                'channel'          => 'walk_in',
                'intake_data'      => ['source' => 'billing'],
            ])->id;
        }

        $bill = Bill::create([
            'id'                => Str::uuid()->toString(),
            'hospital_id'       => $hospitalId,
            'encounter_id'      => $encounterId,
            'patient_id'        => $v['patient_id'],
            'bill_number'       => 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'bill_type'         => $billType,
            'line_items'        => $items,
            'subtotal'          => $subtotal,
            'tax_rate'          => $taxRate,
            'tax_amount'        => $taxAmount,
            'discount_amount'   => $discount,
            'discount_reason'   => $v['discount_reason'] ?? null,
            'insurance_covered' => $insurance,
            'total_amount'      => $total,
            'patient_payable'   => $payable,
            'amount_paid'       => 0,
            'balance_due'       => $payable,
            'payment_status'    => 'pending',
            'currency'          => RegionService::currencyCode(),
            'notes'             => $v['notes'] ?? null,
            'issued_at'         => now(),
        ]);

        return redirect()->route('web.billing.show', $bill->id)->with('success', 'Bill ' . $bill->bill_number . ' created.');
    }

    // ---------------------------------------------------------------
    // Advances / deposits
    // ---------------------------------------------------------------

    public function collectDeposit(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $v = $request->validate([
            'patient_id' => 'required|uuid|exists:patients,id',
            'amount'     => 'required|numeric|min:0.01|max:10000000',
            'method'     => 'required|in:cash,upi,card,bank,cheque',
            'notes'      => 'nullable|string|max:255',
        ]);

        \App\Modules\Billing\Models\PatientDeposit::create([
            'id'               => Str::uuid()->toString(),
            'hospital_id'      => $hospitalId,
            'patient_id'       => $v['patient_id'],
            'amount'           => (float) $v['amount'],
            'type'             => 'collect',
            'method'           => $v['method'],
            'received_by_name' => Auth::user()->name ?? 'Staff',
            'notes'            => $v['notes'] ?? null,
        ]);

        return back()->with('success', 'Deposit of ' . RegionService::currency() . number_format((float) $v['amount'], 2) . ' collected.');
    }

    // ---------------------------------------------------------------
    // Refund / cancel
    // ---------------------------------------------------------------

    public function refundPayment(string $billId, string $paymentId)
    {
        $hospitalId = Auth::user()->hospital_id;
        $bill = Bill::where('hospital_id', $hospitalId)->findOrFail($billId);
        $payment = BillPayment::where('hospital_id', $hospitalId)->where('bill_id', $bill->id)->findOrFail($paymentId);

        if ((float) $payment->amount <= 0) {
            return back()->with('error', 'That ledger entry is already a refund.');
        }

        BillPayment::create([
            'hospital_id'      => $hospitalId,
            'bill_id'          => $bill->id,
            'amount'           => -1 * (float) $payment->amount,
            'method'           => 'refund',
            'reference'        => $payment->reference,
            'received_by'      => Auth::id(),
            'received_by_name' => Auth::user()->name ?? 'Staff',
            'paid_at'          => now(),
            'notes'            => 'Refund of ' . $payment->method . ' payment',
        ]);

        // If the original payment came from the deposit, credit it back.
        if ($payment->method === 'deposit') {
            \App\Modules\Billing\Models\PatientDeposit::create([
                'id'               => Str::uuid()->toString(),
                'hospital_id'      => $hospitalId,
                'patient_id'       => $bill->patient_id,
                'amount'           => (float) $payment->amount,
                'type'             => 'refund',
                'method'           => 'deposit',
                'reference'        => $bill->id,
                'received_by_name' => Auth::user()->name ?? 'Staff',
                'notes'            => 'Refund credit from bill ' . $bill->bill_number,
            ]);
            $bill->update(['deposit_applied' => max(0, round((float) $bill->deposit_applied - (float) $payment->amount, 2))]);
        }

        $this->recomputeBill($bill);

        return back()->with('success', 'Refund of ' . RegionService::currency() . number_format((float) $payment->amount, 2) . ' recorded.');
    }

    public function cancelBill(Request $request, string $billId)
    {
        $hospitalId = Auth::user()->hospital_id;
        $bill = Bill::where('hospital_id', $hospitalId)->findOrFail($billId);
        $v = $request->validate(['cancel_reason' => 'nullable|string|max:255']);

        $bill->update([
            'cancelled_at'  => now(),
            'cancel_reason' => $v['cancel_reason'] ?? 'Cancelled by billing',
        ]);
        $this->recomputeBill($bill);

        return back()->with('success', 'Bill cancelled.');
    }

    /**
     * Billing dashboard — collections, outstanding, breakdowns.
     */
    public function dashboard(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;

        $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to   = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $payments = BillPayment::where('hospital_id', $hospitalId);

        $stats = [
            'today_collection' => (float) (clone $payments)->whereDate('paid_at', today())->sum('amount'),
            'range_collection' => (float) (clone $payments)->whereBetween('paid_at', [$from, $to])->sum('amount'),
            'outstanding'      => (float) Bill::where('hospital_id', $hospitalId)->whereIn('payment_status', ['pending', 'partial'])->sum('balance_due'),
            'bills_pending'    => Bill::where('hospital_id', $hospitalId)->where('payment_status', 'pending')->count(),
            'bills_partial'    => Bill::where('hospital_id', $hospitalId)->where('payment_status', 'partial')->count(),
            'bills_paid'       => Bill::where('hospital_id', $hospitalId)->where('payment_status', 'paid')->count(),
        ];

        // Collection by method within the range.
        $byMethod = (clone $payments)->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('method')->get();

        // Recent payments.
        $recent = (clone $payments)->with('bill.patient')->orderByDesc('paid_at')->limit(15)->get();

        // Top outstanding bills.
        $outstandingBills = Bill::where('hospital_id', $hospitalId)
            ->whereIn('payment_status', ['pending', 'partial'])
            ->with('patient')
            ->orderByDesc('balance_due')
            ->limit(10)->get();

        return view('billing.dashboard', [
            'stats' => $stats, 'byMethod' => $byMethod, 'recent' => $recent,
            'outstandingBills' => $outstandingBills,
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
        ]);
    }

    /**
     * Print receipt — standalone page.
     */
    public function printReceipt(string $id)
    {
        $bill = Bill::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'encounter.doctor'])
            ->findOrFail($id);

        $hospital = Hospital::find(Auth::user()->hospital_id);

        return view('billing.print', compact('bill', 'hospital'));
    }

    /**
     * Print prescription — standalone page.
     */
    public function printPrescription(string $encounterId)
    {
        $encounter = Encounter::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'doctor'])
            ->findOrFail($encounterId);

        $pharmacyOrder = Order::where('encounter_id', $encounterId)
            ->where('type', 'pharmacy')
            ->first();

        $hospital = Hospital::find(Auth::user()->hospital_id);

        return view('billing.prescription-print', compact('encounter', 'pharmacyOrder', 'hospital'));
    }

    /**
     * Discharge summary — standalone page.
     */
    public function dischargeSummary(string $encounterId)
    {
        $encounter = Encounter::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'doctor'])
            ->findOrFail($encounterId);

        $orders = Order::where('encounter_id', $encounterId)->get();
        $bills = Bill::where('encounter_id', $encounterId)->get();
        $hospital = Hospital::find(Auth::user()->hospital_id);

        return view('billing.discharge', compact('encounter', 'orders', 'bills', 'hospital'));
    }

    /**
     * Export bills for import into external accounting software.
     * ?format=csv|tally, optional from/to (issued date) and status.
     */
    public function exportAccounting(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $format = $request->query('format', 'csv');

        $query = Bill::where('hospital_id', $hospitalId)->with('patient');
        if ($request->filled('from')) {
            $query->whereDate('issued_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('issued_at', '<=', $request->query('to'));
        }
        if ($request->filled('status')) {
            $query->where('payment_status', $request->query('status'));
        }
        $bills = $query->orderBy('issued_at')->get();
        $stamp = now()->format('Ymd_His');

        if ($format === 'tally') {
            return response($this->buildTallyXml($bills), 200, [
                'Content-Type'        => 'application/xml',
                'Content-Disposition' => 'attachment; filename="medos_bills_' . $stamp . '.xml"',
            ]);
        }

        return response()->streamDownload(function () use ($bills) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Bill No', 'Date', 'Patient', 'Phone', 'Subtotal', 'Tax', 'Discount', 'Insurance', 'Total', 'Paid', 'Balance', 'Status', 'Method']);
            foreach ($bills as $b) {
                $status = is_object($b->payment_status) ? $b->payment_status->value : $b->payment_status;
                fputcsv($out, [
                    $b->bill_number,
                    optional($b->issued_at)->format('Y-m-d'),
                    $b->patient?->name,
                    $b->patient?->phone,
                    $b->subtotal, $b->tax_amount, $b->discount_amount, $b->insurance_covered,
                    $b->total_amount, $b->amount_paid, $b->balance_due, $status, $b->payment_method,
                ]);
            }
            fclose($out);
        }, 'medos_bills_' . $stamp . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** Build a Tally-importable Sales voucher XML for the given bills. */
    private function buildTallyXml($bills): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $vouchers = '';
        foreach ($bills as $b) {
            $party = $esc($b->patient?->name ?: 'Walk-in Patient');
            $date = optional($b->issued_at ?? $b->created_at)->format('Ymd');
            $total = number_format((float) $b->total_amount, 2, '.', '');
            $vouchers .= <<<XML
      <TALLYMESSAGE xmlns:UDF="TallyUDF">
       <VOUCHER VCHTYPE="Sales" ACTION="Create">
        <DATE>{$date}</DATE>
        <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>
        <VOUCHERNUMBER>{$esc($b->bill_number)}</VOUCHERNUMBER>
        <PARTYLEDGERNAME>{$party}</PARTYLEDGERNAME>
        <ALLLEDGERENTRIES.LIST>
         <LEDGERNAME>{$party}</LEDGERNAME>
         <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
         <AMOUNT>-{$total}</AMOUNT>
        </ALLLEDGERENTRIES.LIST>
        <ALLLEDGERENTRIES.LIST>
         <LEDGERNAME>Sales Account</LEDGERNAME>
         <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
         <AMOUNT>{$total}</AMOUNT>
        </ALLLEDGERENTRIES.LIST>
       </VOUCHER>
      </TALLYMESSAGE>\n
XML;
        }

        return <<<XML
<ENVELOPE>
 <HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>
 <BODY>
  <IMPORTDATA>
   <REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME></REQUESTDESC>
   <REQUESTDATA>
{$vouchers}   </REQUESTDATA>
  </IMPORTDATA>
 </BODY>
</ENVELOPE>
XML;
    }

}

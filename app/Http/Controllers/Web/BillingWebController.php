<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Models\Order;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\RegionService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return view('billing.index', compact('bills'));
    }

    /**
     * Show bill detail.
     */
    public function show(string $id)
    {
        $bill = Bill::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'encounter.doctor'])
            ->findOrFail($id);

        return view('billing.show', compact('bill'));
    }

    /**
     * Create bill form — pre-populate from encounter orders.
     */
    public function create(string $encounterId)
    {
        $encounter = Encounter::where('hospital_id', Auth::user()->hospital_id)
            ->with(['patient', 'doctor'])
            ->findOrFail($encounterId);

        $orders = Order::where('encounter_id', $encounterId)->get();

        $lineItems = [];

        // Consultation fee based on department
        $consultationFee = $this->getConsultationFee($encounter->doctor);
        $lineItems[] = [
            'description' => 'Consultation Fee (' . ($encounter->doctor->department ?? 'General') . ')',
            'quantity'    => 1,
            'unit_price'  => $consultationFee,
            'total'       => $consultationFee,
            'category'    => 'consultation',
        ];

        foreach ($orders as $order) {
            $items = $order->items ?? [];

            if ($order->type === 'pharmacy') {
                foreach ($items as $item) {
                    $price = floatval($item['price'] ?? 0);
                    $qty = intval($item['quantity'] ?? 1);
                    $lineItems[] = [
                        'description' => ($item['name'] ?? 'Medicine') . ' ' . ($item['dosage'] ?? ''),
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'total'       => $price * $qty,
                        'category'    => 'pharmacy',
                    ];
                }
            } else {
                // lab / imaging orders
                foreach ($items as $item) {
                    $testName = $item['name'] ?? $item['test_name'] ?? 'Test';
                    // Look up price from available_tests
                    $price = floatval($item['price'] ?? 0);
                    if ($price <= 0) {
                        $test = DB::table('available_tests')
                            ->where('name', $testName)
                            ->first();
                        $price = $test ? floatval($test->price) : 0;
                    }
                    $lineItems[] = [
                        'description' => $testName,
                        'quantity'    => 1,
                        'unit_price'  => $price,
                        'total'       => $price,
                        'category'    => $order->type ?? 'lab',
                    ];
                }
            }
        }

        $subtotal = collect($lineItems)->sum('total');
        $taxRate = RegionService::taxRate();
        $taxAmount = round($subtotal * $taxRate / 100, 2);

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

        $totalAmount = $request->subtotal + $request->tax_amount - $request->discount_amount;
        $patientPayable = $totalAmount - $request->insurance_covered;

        $bill = Bill::create([
            'id'                => Str::uuid()->toString(),
            'hospital_id'       => Auth::user()->hospital_id,
            'encounter_id'      => $encounter->id,
            'patient_id'        => $encounter->patient_id,
            'bill_number'       => 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
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
     * Record a payment against a bill.
     */
    public function recordPayment(Request $request, string $id)
    {
        $request->validate([
            'method'       => 'required|in:cash,upi,card,insurance',
            'reference'    => 'nullable|string|max:255',
            'amount_paid'  => 'required|numeric|min:0.01',
        ]);

        $bill = Bill::where('hospital_id', Auth::user()->hospital_id)
            ->findOrFail($id);

        $bill->update([
            'payment_status'    => 'paid',
            'payment_method'    => $request->method,
            'payment_reference' => $request->reference,
            'amount_paid'       => $request->amount_paid,
            'balance_due'       => 0,
            'paid_at'           => now(),
        ]);

        return redirect()->back()->with('success', 'Payment recorded successfully.');
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
     * Get consultation fee by department.
     */
    private function getConsultationFee(?Staff $doctor): float
    {
        if (!$doctor) return 500;

        $department = strtolower($doctor->department ?? 'general');

        // Default fees by department — can be moved to config later
        $fees = [
            'pediatrics'     => 500,
            'cardiology'     => 800,
            'orthopedics'    => 700,
            'dermatology'    => 600,
            'ophthalmology'  => 600,
            'ent'            => 500,
            'gynecology'     => 700,
            'neurology'      => 900,
            'general'        => 400,
            'internal medicine' => 500,
        ];

        return $fees[$department] ?? 500;
    }
}

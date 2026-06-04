<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Enums\PaymentStatus;
use App\Modules\Core\Models\Order;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Core\Services\RegionService;
use App\Modules\Patient\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService extends BaseModuleService
{
    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    // ---------------------------------------------------------------
    // Bill Generation
    // ---------------------------------------------------------------

    /**
     * Auto-generate a bill from an encounter.
     *
     * Idempotent: if a bill already exists for the encounter, it is returned
     * unchanged. Line items are built from the consultation fee plus the
     * encounter's lab / imaging / pharmacy orders.
     */
    public function generateBill(Encounter $encounter): Bill
    {
        // Idempotency — never create a second bill for the same encounter.
        $existing = Bill::where('encounter_id', $encounter->id)->first();
        if ($existing) {
            return $existing;
        }

        $data = $this->buildBillData($encounter);

        $bill = Bill::create([
            'id'                => Str::uuid()->toString(),
            'hospital_id'       => $encounter->hospital_id,
            'encounter_id'      => $encounter->id,
            'patient_id'        => $encounter->patient_id,
            'bill_number'       => $this->generateBillNumber(),
            'line_items'        => $data['line_items'],
            'subtotal'          => $data['subtotal'],
            'tax_amount'        => $data['tax_amount'],
            'discount_amount'   => 0,
            'insurance_covered' => 0,
            'total_amount'      => $data['total_amount'],
            'patient_payable'   => $data['total_amount'],
            'amount_paid'       => 0,
            'balance_due'       => $data['total_amount'],
            'payment_status'    => PaymentStatus::Pending,
            'currency'          => RegionService::currencyCode(),
            'issued_at'         => now(),
        ]);

        $this->logInfo('Bill generated', [
            'bill_id'      => $bill->id,
            'encounter_id' => $encounter->id,
            'total'        => $bill->total_amount,
        ]);

        return $bill;
    }

    /**
     * Build the bill line items and totals for an encounter without persisting.
     * Shared by generateBill() and the web "Generate Bill" form so both stay
     * consistent.
     *
     * @return array{line_items: array, subtotal: float, tax_rate: int, tax_amount: float, total_amount: float}
     */
    public function buildBillData(Encounter $encounter): array
    {
        $encounter->loadMissing('doctor');

        $lineItems = [];

        // If the patient booked a treatment package, it is the primary charge and
        // replaces the flat consultation fee. Otherwise bill the department fee.
        $intake  = is_array($encounter->intake_data) ? $encounter->intake_data : [];
        $package = $intake['package'] ?? null;

        if (is_array($package) && ! empty($package['name'])) {
            $packagePrice = (float) ($package['price'] ?? 0);
            $lineItems[] = [
                'description' => 'Package: ' . $package['name'],
                'quantity'    => 1,
                'unit_price'  => $packagePrice,
                'total'       => $packagePrice,
                'category'    => 'consultation',
            ];
        } else {
            $consultationFee = $this->getConsultationFee($encounter->doctor);
            $lineItems[] = [
                'description' => 'Consultation Fee (' . ($encounter->doctor->department ?? 'General') . ')',
                'quantity'    => 1,
                'unit_price'  => $consultationFee,
                'total'       => $consultationFee,
                'category'    => 'consultation',
            ];
        }

        // Lab / imaging / pharmacy charges from the encounter's orders.
        foreach ($this->getOrderCharges($encounter) as $charge) {
            $lineItems[] = $charge;
        }

        $subtotal  = collect($lineItems)->sum('total');
        $taxRate   = RegionService::taxRate();
        $taxAmount = round($subtotal * $taxRate / 100, 2);

        return [
            'line_items'   => $lineItems,
            'subtotal'     => $subtotal,
            'tax_rate'     => $taxRate,
            'tax_amount'   => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ];
    }

    /**
     * Build line items from an encounter's lab / imaging / pharmacy orders.
     */
    protected function getOrderCharges(Encounter $encounter): array
    {
        $charges = [];

        $orders = Order::where('encounter_id', $encounter->id)->get();

        foreach ($orders as $order) {
            $items = $order->items ?? [];

            if ($order->type === 'pharmacy') {
                foreach ($items as $item) {
                    $price = (float) ($item['price'] ?? 0);
                    $qty   = (int) ($item['quantity'] ?? 1);
                    $charges[] = [
                        'description' => trim(($item['name'] ?? 'Medicine') . ' ' . ($item['dosage'] ?? '')),
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
                    $price    = (float) ($item['price'] ?? 0);
                    if ($price <= 0) {
                        $test  = DB::table('available_tests')->where('name', $testName)->first();
                        $price = $test ? (float) $test->price : 0;
                    }
                    $charges[] = [
                        'description' => $testName,
                        'quantity'    => 1,
                        'unit_price'  => $price,
                        'total'       => $price,
                        'category'    => $order->type ?? 'lab',
                    ];
                }
            }
        }

        return $charges;
    }

    // ---------------------------------------------------------------
    // Line Items
    // ---------------------------------------------------------------

    /**
     * Add a charge/line item to an existing bill.
     */
    public function addCharge(
        Bill $bill,
        string $description,
        string $code,
        float $amount,
        string $category,
    ): Bill {
        $bill->addLineItem([
            'description' => $description,
            'code'        => $code,
            'category'    => $category,
            'quantity'    => 1,
            'unit_price'  => $amount,
            'total'       => $amount,
        ]);

        $bill->calculateTotals();

        $this->logInfo('Charge added to bill', [
            'bill_id'     => $bill->id,
            'code'        => $code,
            'amount'      => $amount,
        ]);

        return $bill->fresh();
    }

    // ---------------------------------------------------------------
    // Insurance Coverage
    // ---------------------------------------------------------------

    /**
     * Apply insurance coverage to a bill based on eligibility result.
     */
    public function applyInsuranceCoverage(Bill $bill, array $insuranceResult): Bill
    {
        if (empty($insuranceResult['eligible']) || !$insuranceResult['eligible']) {
            $this->logInfo('Insurance not eligible, no coverage applied', [
                'bill_id' => $bill->id,
            ]);
            return $bill;
        }

        $copayPercent = $insuranceResult['copay_percent'] ?? 0;
        $subtotal = (float) $bill->subtotal;

        // Insurance covers (100 - copay)% of the subtotal
        $coveragePercent = 100 - $copayPercent;
        $insuranceCoverage = round($subtotal * ($coveragePercent / 100), 2);

        $bill->insurance_covered_amount = $insuranceCoverage;
        $bill->total_amount = $subtotal
            + (float) $bill->tax_amount
            - (float) $bill->discount_amount
            - $insuranceCoverage;

        // Ensure total does not go negative
        if ($bill->total_amount < 0) {
            $bill->total_amount = 0;
        }

        $bill->balance_due = $bill->total_amount - (float) $bill->amount_paid;
        $bill->save();

        $this->logInfo('Insurance coverage applied', [
            'bill_id'   => $bill->id,
            'coverage'  => $insuranceCoverage,
            'copay'     => $copayPercent,
        ]);

        return $bill->fresh();
    }

    // ---------------------------------------------------------------
    // Discounts
    // ---------------------------------------------------------------

    /**
     * Apply a discount to a bill.
     */
    public function applyDiscount(Bill $bill, float $amount, string $reason): Bill
    {
        $bill->discount_amount = (float) $bill->discount_amount + $amount;

        $notes = $bill->notes ?? '';
        $bill->notes = trim($notes . "\nDiscount: {$reason} (-{$amount})");

        // Recalculate totals
        $bill->total_amount = (float) $bill->subtotal
            + (float) $bill->tax_amount
            - (float) $bill->discount_amount
            - (float) $bill->insurance_covered_amount;

        if ($bill->total_amount < 0) {
            $bill->total_amount = 0;
        }

        $bill->balance_due = $bill->total_amount - (float) $bill->amount_paid;
        $bill->save();

        $this->logInfo('Discount applied', [
            'bill_id' => $bill->id,
            'amount'  => $amount,
            'reason'  => $reason,
        ]);

        return $bill->fresh();
    }

    // ---------------------------------------------------------------
    // Payments
    // ---------------------------------------------------------------

    /**
     * Process a payment against a bill.
     */
    public function processPayment(Bill $bill, string $method, ?string $reference = null): Bill
    {
        $bill->markPaid($method, $reference);

        $this->logInfo('Payment processed', [
            'bill_id'   => $bill->id,
            'method'    => $method,
            'reference' => $reference,
            'amount'    => $bill->total_amount,
        ]);

        return $bill->fresh();
    }

    /**
     * Generate a payment link for online payment (UPI / card).
     *
     * TODO: Integrate with actual payment gateway (Razorpay, Stripe, PayTabs, etc.)
     */
    public function generatePaymentLink(Bill $bill): string
    {
        // TODO: Replace stub with real payment gateway integration
        // Example for Razorpay:
        //   $paymentLink = $razorpay->paymentLink->create([
        //       'amount' => $bill->balance_due * 100,
        //       'currency' => $bill->currency,
        //       'description' => "Bill #{$bill->bill_number}",
        //   ]);

        $token = Str::random(32);

        $this->logInfo('Payment link generated', [
            'bill_id' => $bill->id,
            'token'   => $token,
        ]);

        // Stub: return a placeholder URL
        return url("/pay/{$bill->id}/{$token}");
    }

    // ---------------------------------------------------------------
    // Bill Summary
    // ---------------------------------------------------------------

    /**
     * Get a formatted bill summary suitable for WhatsApp or display.
     */
    public function getBillSummary(Bill $bill): array
    {
        $bill->load(['patient', 'encounter']);

        $lineItemsSummary = [];
        foreach ($bill->line_items ?? [] as $item) {
            $lineItemsSummary[] = [
                'description' => $item['description'] ?? '',
                'amount'      => $item['total'] ?? 0,
                'category'    => $item['category'] ?? 'other',
            ];
        }

        return [
            'bill_number'     => $bill->bill_number,
            'patient_name'    => $bill->patient?->name ?? 'N/A',
            'date'            => $bill->issued_at?->format('d M Y') ?? now()->format('d M Y'),
            'line_items'      => $lineItemsSummary,
            'subtotal'        => (float) $bill->subtotal,
            'tax'             => (float) $bill->tax_amount,
            'discount'        => (float) $bill->discount_amount,
            'insurance'       => (float) $bill->insurance_covered_amount,
            'total'           => (float) $bill->total_amount,
            'paid'            => (float) $bill->amount_paid,
            'balance_due'     => (float) $bill->balance_due,
            'payment_status'  => $bill->payment_status?->label() ?? 'Pending',
            'currency'        => $bill->currency ?? 'AED',
            'whatsapp_text'   => $this->formatBillForWhatsApp($bill),
        ];
    }

    // ---------------------------------------------------------------
    // Revenue Analytics
    // ---------------------------------------------------------------

    /**
     * Get revenue summary for a date range, optionally filtered by department.
     */
    public function getRevenueSummary(
        Carbon $from,
        Carbon $to,
        ?string $departmentFilter = null,
    ): array {
        $hospitalId = $this->requireHospital();

        $query = Bill::where('hospital_id', $hospitalId)
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($departmentFilter) {
            // Filter by department via encounter relationship
            $query->whereHas('encounter', function ($q) use ($departmentFilter) {
                $q->where('department', $departmentFilter);
            });
        }

        $bills = $query->get();

        $totalRevenue = $bills->sum('total_amount');
        $totalPaid = $bills->sum('amount_paid');
        $totalOutstanding = $bills->sum('balance_due');
        $totalInsurance = $bills->sum('insurance_covered_amount');
        $totalDiscount = $bills->sum('discount_amount');
        $totalTax = $bills->sum('tax_amount');
        $billCount = $bills->count();

        // Revenue by category
        $revenueByCategory = [];
        foreach ($bills as $bill) {
            foreach ($bill->line_items ?? [] as $item) {
                $category = $item['category'] ?? 'other';
                if (!isset($revenueByCategory[$category])) {
                    $revenueByCategory[$category] = 0;
                }
                $revenueByCategory[$category] += $item['total'] ?? 0;
            }
        }

        // Revenue by payment status
        $revenueByStatus = $bills->groupBy(function ($bill) {
            return $bill->payment_status?->value ?? 'pending';
        })->map(fn ($group) => [
            'count'  => $group->count(),
            'amount' => $group->sum('total_amount'),
        ])->toArray();

        // Daily breakdown
        $dailyRevenue = $bills->groupBy(function ($bill) {
            return $bill->issued_at?->format('Y-m-d') ?? 'unknown';
        })->map(fn ($group) => [
            'count'    => $group->count(),
            'revenue'  => $group->sum('total_amount'),
            'collected'=> $group->sum('amount_paid'),
        ])->toArray();

        return [
            'period'              => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'department'          => $departmentFilter,
            'total_bills'         => $billCount,
            'total_revenue'       => round((float) $totalRevenue, 2),
            'total_collected'     => round((float) $totalPaid, 2),
            'total_outstanding'   => round((float) $totalOutstanding, 2),
            'total_insurance'     => round((float) $totalInsurance, 2),
            'total_discounts'     => round((float) $totalDiscount, 2),
            'total_tax'           => round((float) $totalTax, 2),
            'average_bill'        => $billCount > 0 ? round((float) $totalRevenue / $billCount, 2) : 0,
            'collection_rate'     => $totalRevenue > 0 ? round(((float) $totalPaid / (float) $totalRevenue) * 100, 1) : 0,
            'revenue_by_category' => $revenueByCategory,
            'revenue_by_status'   => $revenueByStatus,
            'daily_breakdown'     => $dailyRevenue,
        ];
    }

    // ---------------------------------------------------------------
    // Internal Helpers
    // ---------------------------------------------------------------

    /**
     * Get the consultation fee for the attending doctor based on department.
     */
    protected function getConsultationFee(?\App\Modules\Core\Models\Staff $doctor): float
    {
        if (! $doctor) {
            return 500;
        }

        $department = strtolower($doctor->department ?? 'general');

        $fees = [
            'pediatrics'        => 500,
            'cardiology'        => 800,
            'orthopedics'       => 700,
            'dermatology'       => 600,
            'ophthalmology'     => 600,
            'ent'               => 500,
            'gynecology'        => 700,
            'neurology'         => 900,
            'general'           => 400,
            'internal medicine' => 500,
        ];

        return $fees[$department] ?? 500;
    }

    /**
     * Generate a unique bill number.
     */
    protected function generateBillNumber(): string
    {
        $prefix = 'BILL';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Format a bill for WhatsApp message.
     */
    protected function formatBillForWhatsApp(Bill $bill): string
    {
        $lines = [];
        $lines[] = "*Bill #{$bill->bill_number}*";
        $lines[] = "Date: " . ($bill->issued_at?->format('d M Y') ?? 'N/A');
        $lines[] = "";

        foreach ($bill->line_items ?? [] as $item) {
            $desc = $item['description'] ?? 'Item';
            $total = number_format($item['total'] ?? 0, 2);
            $lines[] = "- {$desc}: {$bill->currency} {$total}";
        }

        $lines[] = "";
        $lines[] = "Subtotal: {$bill->currency} " . number_format((float) $bill->subtotal, 2);

        if ((float) $bill->tax_amount > 0) {
            $lines[] = "Tax: {$bill->currency} " . number_format((float) $bill->tax_amount, 2);
        }
        if ((float) $bill->discount_amount > 0) {
            $lines[] = "Discount: -{$bill->currency} " . number_format((float) $bill->discount_amount, 2);
        }
        if ((float) $bill->insurance_covered_amount > 0) {
            $lines[] = "Insurance: -{$bill->currency} " . number_format((float) $bill->insurance_covered_amount, 2);
        }

        $lines[] = "*Total Due: {$bill->currency} " . number_format((float) $bill->balance_due, 2) . "*";

        return implode("\n", $lines);
    }
}

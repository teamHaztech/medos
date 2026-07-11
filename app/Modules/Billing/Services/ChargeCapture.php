<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Models\ChargeItem;
use App\Modules\Billing\Models\ServiceCharge;
use App\Modules\Core\Models\Order;
use App\Modules\Core\Services\RegionService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Pharmacy\Models\PharmacyStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Revenue-cycle charge capture: the single entry point every clinical event uses
 * to post a chargeable line into the ledger, and the compiler that turns pending
 * charges into a Bill. Reuses Bill + the BillPayment ledger + the ERP sync
 * (BillObserver) unchanged — a compiled Bill flows to the external ERP as before.
 */
class ChargeCapture
{
    /**
     * Post a charge to the ledger. Idempotent by (hospital_id, source, source_ref):
     * re-posting the same event updates the pending row instead of duplicating, and
     * never touches a charge that has already been billed.
     *
     * Required keys: hospital_id, patient_id, source, description.
     * Optional: encounter_id, admission_id, service_charge_id, code, category (unused
     * here but accepted), unit_price, quantity, is_taxable, source_ref, posted_by_name.
     * If a `code` or `service_charge_id` is given and no explicit unit_price, the price
     * is resolved from the ServiceCharge rate card.
     */
    public function post(array $p): ChargeItem
    {
        $hid    = $p['hospital_id'];
        $source = $p['source'];
        $ref    = $p['source_ref'] ?? null;

        // Rate-card price resolution when no explicit price was supplied.
        $price     = array_key_exists('unit_price', $p) ? (float) $p['unit_price'] : null;
        $taxable   = $p['is_taxable'] ?? false;
        $code      = $p['code'] ?? null;
        $serviceId = $p['service_charge_id'] ?? null;

        if ($price === null && ($code || $serviceId)) {
            $rate = $this->priceFor($hid, $code, null, null, $serviceId);
            if ($rate) {
                $price     = $rate['price'];
                $taxable   = $rate['is_taxable'];
                $code      = $code ?? $rate['code'];
                $serviceId = $serviceId ?? $rate['service_charge_id'];
            }
        }
        $price = (float) ($price ?? 0);
        $qty   = (float) ($p['quantity'] ?? 1);

        $attrs = [
            'patient_id'        => $p['patient_id'],
            'encounter_id'      => $p['encounter_id'] ?? null,
            'admission_id'      => $p['admission_id'] ?? null,
            'service_charge_id' => $serviceId,
            'source'            => $source,
            'description'       => $p['description'],
            'code'              => $code,
            'quantity'          => $qty,
            'unit_price'        => $price,
            'total'             => round($qty * $price, 2),
            'is_taxable'        => (bool) $taxable,
            'posted_by_name'    => $p['posted_by_name'] ?? null,
            'posted_at'         => now(),
        ];

        // Idempotency on the originating event.
        if ($ref !== null) {
            $existing = ChargeItem::where('hospital_id', $hid)
                ->where('source', $source)->where('source_ref', $ref)->first();
            if ($existing) {
                if ($existing->status === ChargeItem::STATUS_BILLED) {
                    return $existing; // already invoiced — leave it alone
                }
                $existing->update($attrs);

                return $existing->fresh();
            }
        }

        return ChargeItem::create(array_merge($attrs, [
            'id'          => Str::uuid()->toString(),
            'hospital_id' => $hid,
            'source_ref'  => $ref,
            'status'      => ChargeItem::STATUS_PENDING,
        ]));
    }

    /**
     * Pending (unbilled) charges + running total for a scope.
     * $scope e.g. ['encounter_id' => $id] | ['admission_id' => $id] | ['patient_id' => $id].
     *
     * @return array{items: \Illuminate\Support\Collection, total: float}
     */
    public function pendingFor(string $hospitalId, array $scope): array
    {
        $q = ChargeItem::where('hospital_id', $hospitalId)
            ->where('status', ChargeItem::STATUS_PENDING);
        foreach ($scope as $col => $val) {
            $q->where($col, $val);
        }
        $items = $q->orderBy('posted_at')->get();

        return ['items' => $items, 'total' => round((float) $items->sum('total'), 2)];
    }

    /**
     * Compile all of an encounter's charges into its Bill (create or refresh),
     * mark them billed, and recompute payment state. Returns the Bill (or null if
     * there is nothing to bill).
     */
    public function compileBill(Encounter $encounter, ?string $postedByName = null): ?Bill
    {
        $hid = $encounter->hospital_id;

        $charges = ChargeItem::where('hospital_id', $hid)
            ->where('encounter_id', $encounter->id)
            ->where('status', '!=', ChargeItem::STATUS_CANCELLED)
            ->orderBy('posted_at')->get();

        $bill = Bill::withoutGlobalScopes()->where('encounter_id', $encounter->id)->first();

        if ($charges->isEmpty()) {
            return $bill; // nothing to compile
        }

        $items = $charges->map(fn (ChargeItem $c) => [
            'description' => $c->description,
            'code'        => $c->code,
            'category'    => $c->source,
            'quantity'    => (float) $c->quantity,
            'unit_price'  => (float) $c->unit_price,
            'total'       => round((float) $c->quantity * (float) $c->unit_price, 2),
            'taxable'     => (bool) $c->is_taxable,
        ])->values()->all();

        $subtotal    = round(collect($items)->sum('total'), 2);
        $taxRate     = round((float) RegionService::taxRate(), 2);
        $taxableBase = round(collect($items)->where('taxable', true)->sum('total'), 2);
        $taxAmount   = round($taxableBase * $taxRate / 100, 2);
        $discount    = $bill ? round((float) $bill->discount_amount, 2) : 0.0;
        $insurance   = $bill ? round((float) $bill->insurance_covered, 2) : 0.0;
        $total       = round($subtotal + $taxAmount - $discount, 2);
        $payable     = max(0, round($total - $insurance, 2));

        if (! $bill) {
            $bill = Bill::create([
                'id'                => Str::uuid()->toString(),
                'hospital_id'       => $hid,
                'encounter_id'      => $encounter->id,
                'patient_id'        => $encounter->patient_id,
                'bill_number'       => 'BILL-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                'bill_type'         => 'encounter',
                'line_items'        => $items,
                'subtotal'          => $subtotal,
                'tax_rate'          => $taxRate,
                'tax_amount'        => $taxAmount,
                'discount_amount'   => 0,
                'insurance_covered' => 0,
                'total_amount'      => $total,
                'patient_payable'   => $payable,
                'amount_paid'       => 0,
                'balance_due'       => $payable,
                'payment_status'    => 'pending',
                'currency'          => RegionService::currencyCode(),
                'issued_at'         => now(),
            ]);
        } else {
            $bill->update([
                'line_items'      => $items,
                'subtotal'        => $subtotal,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $taxAmount,
                'total_amount'    => $total,
                'patient_payable' => $payable,
            ]);
        }

        ChargeItem::whereIn('id', $charges->pluck('id'))
            ->where('status', ChargeItem::STATUS_PENDING)
            ->update(['status' => ChargeItem::STATUS_BILLED, 'bill_id' => $bill->id]);

        $this->recomputePayments($bill->fresh());

        return $bill->fresh();
    }

    // ---------------------------------------------------------------
    // Source capture helpers — the OPD auto-capture hooks (Phase 1)
    // ---------------------------------------------------------------

    /**
     * Capture the consultation charge for an encounter, priced from the rate card
     * (consultation category, matched to the doctor's department) or a booked package,
     * falling back to a default department fee. Idempotent per encounter.
     */
    public function captureConsultation(Encounter $encounter, $doctor = null, ?string $postedByName = null): ChargeItem
    {
        $dept   = $doctor?->department;
        $intake = is_array($encounter->intake_data) ? $encounter->intake_data : [];
        $package = $intake['package'] ?? null;

        if (is_array($package) && ! empty($package['name'])) {
            return $this->post([
                'hospital_id'   => $encounter->hospital_id,
                'patient_id'    => $encounter->patient_id,
                'encounter_id'  => $encounter->id,
                'source'        => 'consultation',
                'description'   => 'Package: ' . $package['name'],
                'unit_price'    => (float) ($package['price'] ?? 0),
                'source_ref'    => 'consult:' . $encounter->id,
                'posted_by_name' => $postedByName,
            ]);
        }

        $rate  = $this->priceFor($encounter->hospital_id, null, 'consultation', $dept);
        $price = $rate['price'] ?? $this->defaultConsultationFee($dept);

        return $this->post([
            'hospital_id'    => $encounter->hospital_id,
            'patient_id'     => $encounter->patient_id,
            'encounter_id'   => $encounter->id,
            'source'         => 'consultation',
            'description'    => 'Consultation Fee' . ($dept ? ' (' . $dept . ')' : ''),
            'unit_price'     => $price,
            'is_taxable'     => $rate['is_taxable'] ?? false,
            'service_charge_id' => $rate['service_charge_id'] ?? null,
            'source_ref'     => 'consult:' . $encounter->id,
            'posted_by_name' => $postedByName,
        ]);
    }

    /**
     * Capture a charge for each test in a lab / imaging / procedure order. Price from
     * the order item → available_tests → rate card. Idempotent per (order, item).
     */
    public function captureOrder(Order $order, ?string $postedByName = null): void
    {
        $type = $order->type instanceof \BackedEnum ? $order->type->value : $order->type;
        if (! in_array($type, ['lab', 'imaging', 'procedure'], true)) {
            return;
        }
        $items = is_array($order->items) ? $order->items : (json_decode($order->items ?? '[]', true) ?: []);

        foreach ($items as $idx => $item) {
            $name  = $item['name'] ?? $item['test_name'] ?? 'Test';
            $price = (float) ($item['price'] ?? 0);
            if ($price <= 0) {
                $test  = DB::table('available_tests')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                $price = $test ? (float) $test->price : 0.0;
                if ($price <= 0) {
                    $rate  = $this->priceFor($order->hospital_id, null, 'investigation', $name);
                    $price = $rate['price'] ?? 0.0;
                }
            }
            $this->post([
                'hospital_id'    => $order->hospital_id,
                'patient_id'     => $order->patient_id,
                'encounter_id'   => $order->encounter_id,
                'source'         => $type === 'imaging' ? 'imaging' : ($type === 'procedure' ? 'procedure' : 'lab'),
                'description'    => $name,
                'unit_price'     => $price,
                'source_ref'     => 'order:' . $order->id . ':' . $idx,
                'posted_by_name' => $postedByName,
            ]);
        }
    }

    /**
     * Capture a pharmacy charge per dispensed item at its selling price. Called on
     * dispense — the point of sale. Idempotent per (order, item).
     */
    public function capturePharmacyDispense(Order $order, ?string $postedByName = null): void
    {
        $items = is_array($order->items) ? $order->items : (json_decode($order->items ?? '[]', true) ?: []);

        foreach ($items as $idx => $item) {
            $name = is_array($item) ? ($item['name'] ?? null) : null;
            if (! $name) {
                continue;
            }
            $qty   = max(1, (int) (is_array($item) ? ($item['quantity'] ?? $item['qty'] ?? 1) : 1));
            $price = (float) ($item['price'] ?? 0);
            if ($price <= 0) {
                $medicineId = DB::table('medicines')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
                if ($medicineId) {
                    $price = (float) (PharmacyStock::where('hospital_id', $order->hospital_id)
                        ->where('medicine_id', $medicineId)
                        ->where('selling_price', '>', 0)
                        ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                        ->value('selling_price') ?? 0);
                }
            }
            $dosage = is_array($item) ? trim((string) ($item['dosage'] ?? '')) : '';
            $desc = ($dosage !== '' && stripos($name, $dosage) === false) ? trim($name . ' ' . $dosage) : $name;
            $this->post([
                'hospital_id'    => $order->hospital_id,
                'patient_id'     => $order->patient_id,
                'encounter_id'   => $order->encounter_id,
                'source'         => 'pharmacy',
                'description'    => $desc,
                'unit_price'     => $price,
                'quantity'       => $qty,
                'source_ref'     => 'disp:' . $order->id . ':' . $idx,
                'posted_by_name' => $postedByName,
            ]);
        }
    }

    /** Fallback consultation fee by department when the rate card has no consultation service. */
    private function defaultConsultationFee(?string $dept): float
    {
        $map = [
            'pediatrics' => 500, 'cardiology' => 800, 'orthopedics' => 700, 'orthopaedics' => 700,
            'dermatology' => 600, 'gynecology' => 700, 'gynaecology' => 700, 'neurology' => 900,
            'general medicine' => 400, 'ent' => 500, 'ophthalmology' => 600, 'psychiatry' => 800,
        ];

        return (float) ($map[strtolower(trim((string) $dept))] ?? 500);
    }

    /**
     * Resolve a price from the ServiceCharge rate card. Lookup order:
     * explicit service_charge_id → exact code → category (+ optional name hint).
     *
     * @return array{name:string, price:float, is_taxable:bool, code:?string, service_charge_id:string}|null
     */
    public function priceFor(
        string $hospitalId,
        ?string $code = null,
        ?string $category = null,
        ?string $nameHint = null,
        ?string $serviceChargeId = null,
    ): ?array {
        $q = ServiceCharge::where('hospital_id', $hospitalId)->where('is_active', true);

        if ($serviceChargeId) {
            $svc = (clone $q)->where('id', $serviceChargeId)->first();
        } elseif ($code) {
            $svc = (clone $q)->where('code', $code)->first();
        } else {
            $c = clone $q;
            if ($category) {
                $c->where('category', $category);
            }
            if ($nameHint) {
                $c->where('name', 'like', '%' . $nameHint . '%');
            }
            $svc = $c->orderBy('price')->first();
        }

        if (! $svc) {
            return null;
        }

        return [
            'name'              => $svc->name,
            'price'             => (float) $svc->price,
            'is_taxable'        => (bool) $svc->is_taxable,
            'code'              => $svc->code,
            'service_charge_id' => $svc->id,
        ];
    }

    /**
     * Recompute amount_paid / balance_due / payment_status from the payment ledger.
     * Mirrors BillingWebController::recomputeBill so the ledger stays the single
     * source of truth for payment state.
     */
    private function recomputePayments(Bill $bill): void
    {
        $paid     = round((float) $bill->payments()->sum('amount'), 2);
        $refunded = round((float) abs($bill->payments()->where('amount', '<', 0)->sum('amount')), 2);
        $payable  = round((float) $bill->patient_payable, 2);
        $balance  = max(0, round($payable - $paid, 2));
        $last     = $bill->payments()->where('amount', '>', 0)->orderByDesc('paid_at')->first();

        if ($bill->isCancelled()) {
            $status = 'cancelled';
        } elseif ($refunded > 0 && $paid <= 0.009) {
            $status = 'refunded';
        } elseif ($paid > 0 && $balance <= 0.009) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
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
}

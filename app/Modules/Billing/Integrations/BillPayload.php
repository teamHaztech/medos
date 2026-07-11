<?php

namespace App\Modules\Billing\Integrations;

use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Enums\PaymentStatus;

/**
 * Canonical, connector-agnostic representation of a bill sent to external
 * systems (and reused by the accounting export). Stable shape so integrators
 * can rely on it.
 */
class BillPayload
{
    public static function fromBill(Bill $bill, string $event): array
    {
        $bill->loadMissing('patient');
        $status = $bill->payment_status instanceof PaymentStatus
            ? $bill->payment_status->value
            : $bill->payment_status;

        return [
            'event'       => $event,
            'source'      => 'medos',
            'occurred_at' => now()->toIso8601String(),
            'hospital_id' => $bill->hospital_id,
            'bill'        => [
                'id'                => $bill->id,
                'bill_number'       => $bill->bill_number,
                'bill_type'         => $bill->bill_type,
                'currency'          => $bill->currency,
                'patient'           => [
                    'id'    => $bill->patient_id,
                    'name'  => $bill->patient?->name,
                    'phone' => $bill->patient?->phone,
                ],
                'line_items'        => $bill->line_items ?? [],
                'subtotal'          => (float) $bill->subtotal,
                'tax_rate'          => (float) $bill->tax_rate,
                'tax_amount'        => (float) $bill->tax_amount,
                'discount_amount'   => (float) $bill->discount_amount,
                'discount_reason'   => $bill->discount_reason,
                'insurance_covered' => (float) $bill->insurance_covered,
                'deposit_applied'   => (float) $bill->deposit_applied,
                'total_amount'      => (float) $bill->total_amount,
                'amount_paid'       => (float) $bill->amount_paid,
                'balance_due'       => (float) $bill->balance_due,
                'payment_status'    => $status,
                'payment_method'    => $bill->payment_method,
                'issued_at'         => optional($bill->issued_at)->toIso8601String(),
                'paid_at'           => optional($bill->paid_at)->toIso8601String(),
            ],
        ];
    }
}

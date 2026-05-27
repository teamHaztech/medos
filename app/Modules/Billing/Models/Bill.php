<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Enums\PaymentStatus;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'bills';

    protected $fillable = [
        'id',
        'hospital_id',
        'encounter_id',
        'patient_id',
        'bill_number',
        'line_items',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'insurance_covered',
        'patient_payable',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_status',
        'payment_method',
        'payment_reference',
        'currency',
        'notes',
        'issued_at',
        'paid_at',
    ];

    protected $casts = [
        'line_items'               => 'array',
        'subtotal'                 => 'decimal:2',
        'tax_amount'               => 'decimal:2',
        'discount_amount'          => 'decimal:2',
        'insurance_covered'        => 'decimal:2',
        'patient_payable'          => 'decimal:2',
        'total_amount'             => 'decimal:2',
        'amount_paid'              => 'decimal:2',
        'balance_due'              => 'decimal:2',
        'payment_status'           => PaymentStatus::class,
        'issued_at'                => 'datetime',
        'paid_at'                  => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this bill.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the encounter for this bill.
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * Get the patient for this bill.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Add a line item to the bill.
     */
    public function addLineItem(array $item): self
    {
        $lineItems = $this->line_items ?? [];

        $lineItems[] = array_merge([
            'description' => '',
            'quantity'    => 1,
            'unit_price'  => 0,
            'total'       => 0,
        ], $item);

        $this->line_items = $lineItems;
        $this->save();

        return $this;
    }

    /**
     * Recalculate totals from line items.
     */
    public function calculateTotals(): self
    {
        $lineItems = $this->line_items ?? [];

        $subtotal = 0;
        foreach ($lineItems as $item) {
            $subtotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
        }

        $this->subtotal = $subtotal;
        $this->total_amount = $subtotal
            + ($this->tax_amount ?? 0)
            - ($this->discount_amount ?? 0)
            - ($this->insurance_covered ?? 0);
        $this->balance_due = $this->total_amount - ($this->amount_paid ?? 0);

        $this->save();

        return $this;
    }

    /**
     * Mark the bill as paid.
     */
    public function markPaid(string $method, ?string $reference = null): self
    {
        $this->payment_status = PaymentStatus::Paid;
        $this->payment_method = $method;
        $this->payment_reference = $reference;
        $this->amount_paid = $this->total_amount;
        $this->balance_due = 0;
        $this->paid_at = now();
        $this->save();

        return $this;
    }
}

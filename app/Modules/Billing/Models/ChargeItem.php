<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single captured charge in the revenue-cycle ledger. Every chargeable event
 * (consultation, lab test, dispensed medicine, room-day, procedure, …) posts one
 * row here; a Bill is later compiled from the pending charges. Uses only HasUuid
 * with manual hospital scoping to avoid the null global-scope trap.
 */
class ChargeItem extends Model
{
    use HasUuid;

    protected $table = 'charge_items';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'encounter_id', 'admission_id',
        'service_charge_id', 'bill_id', 'source', 'source_ref', 'description', 'code',
        'quantity', 'unit_price', 'total', 'is_taxable', 'status', 'posted_by_name', 'posted_at',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total'      => 'decimal:2',
        'is_taxable' => 'boolean',
        'posted_at'  => 'datetime',
    ];

    public const SOURCES = [
        'registration' => 'Registration',
        'consultation' => 'Consultation',
        'lab'          => 'Laboratory',
        'imaging'      => 'Imaging',
        'pharmacy'     => 'Pharmacy',
        'procedure'    => 'Procedure',
        'room'         => 'Room / Bed',
        'nursing'      => 'Nursing',
        'consumable'   => 'Consumable',
        'other'        => 'Other',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_BILLED    = 'billed';
    public const STATUS_CANCELLED = 'cancelled';

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst($this->source);
    }
}

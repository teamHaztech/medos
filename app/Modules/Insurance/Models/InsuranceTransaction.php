<?php

namespace App\Modules\Insurance\Models;

use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceTransaction extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'insurance_transactions';

    protected $fillable = [
        'hospital_id',
        'encounter_id',
        'patient_id',
        'provider_name',
        'policy_number',
        'transaction_type',
        'status',
        'requested_amount',
        'approved_amount',
        'denial_reason',
        'authorization_number',
        'request_payload',
        'response_payload',
        'diagnosis_codes',
        'procedure_codes',
        'submitted_at',
        'responded_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount'  => 'decimal:2',
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'diagnosis_codes'  => 'array',
        'procedure_codes'  => 'array',
        'submitted_at'     => 'datetime',
        'responded_at'     => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this insurance transaction.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the encounter for this insurance transaction.
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * Get the patient for this insurance transaction.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Determine if the transaction has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Determine if the transaction is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Mark the transaction as approved with the given amount.
     */
    public function markApproved(float $amount): self
    {
        $this->status = 'approved';
        $this->approved_amount = $amount;
        $this->responded_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark the transaction as denied with the given reason.
     */
    public function markDenied(string $reason): self
    {
        $this->status = 'denied';
        $this->denial_reason = $reason;
        $this->responded_at = now();
        $this->save();

        return $this;
    }
}

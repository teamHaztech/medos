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

    // Real columns match the migration. `provider_name`, `transaction_type` and
    // `authorization_number` are accepted as aliases (see mutators/accessors below)
    // so the older stubbed InsuranceService keeps working while storing to the
    // correct columns (insurer_code / type / external_reference_id).
    protected $fillable = [
        'id',
        'hospital_id',
        'encounter_id',
        'patient_id',
        'insurer_code',
        'insurer_name',
        'policy_number',
        'member_id',
        'type',
        'status',
        'requested_amount',
        'approved_amount',
        'patient_copay',
        'denial_reason',
        'external_reference_id',
        'request_payload',
        'response_payload',
        'diagnosis_codes',
        'procedure_codes',
        'submitted_at',
        'responded_at',
        // aliases (back-compat)
        'provider_name',
        'transaction_type',
        'authorization_number',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount'  => 'decimal:2',
        'patient_copay'    => 'decimal:2',
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'diagnosis_codes'  => 'array',
        'procedure_codes'  => 'array',
        'submitted_at'     => 'datetime',
        'responded_at'     => 'datetime',
    ];

    public const TYPES = [
        'eligibility_check'  => 'Eligibility Check',
        'pre_authorization'  => 'Pre-Authorization',
        'claim_submission'   => 'Claim',
        'claim_follow_up'    => 'Claim Follow-up',
    ];

    // --- back-compat aliases: old drifted names → real columns ---
    public function setProviderNameAttribute($v): void { $this->attributes['insurer_code'] = $v; }
    public function getProviderNameAttribute() { return $this->attributes['insurer_code'] ?? null; }
    public function setTransactionTypeAttribute($v): void { $this->attributes['type'] = $v; }
    public function getTransactionTypeAttribute() { return $this->attributes['type'] ?? null; }
    public function setAuthorizationNumberAttribute($v): void { $this->attributes['external_reference_id'] = $v; }
    public function getAuthorizationNumberAttribute() { return $this->attributes['external_reference_id'] ?? null; }

    public function bill(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Billing\Models\Bill::class, 'insurance_transaction_id');
    }

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

<?php

namespace App\Modules\Patient\Models;

use App\Modules\AIReceptionist\Models\Conversation;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Enums\EncounterStatus;
use App\Modules\Core\Enums\EncounterType;
use App\Modules\Core\Enums\TriageClassification;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Insurance\Models\InsuranceTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Encounter extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'encounters';

    protected $fillable = [
        'id',
        'hospital_id',
        'patient_id',
        'doctor_id',
        'encounter_number',
        'type',
        'status',
        'channel',
        'intake_data',
        'triage_score',
        'triage_classification',
        'triage_reasoning',
        'soap_notes',
        'vitals',
        'patient_advice',
        'diagnosis_codes',
        'referral_to',
        'follow_up_date',
        'follow_up_notes',
        'abha_number',
        'abha_linked',
    ];

    protected $casts = [
        'type'                   => EncounterType::class,
        'status'                 => EncounterStatus::class,
        'triage_classification'  => TriageClassification::class,
        'intake_data'            => 'array',
        'triage_reasoning'       => 'array',
        'soap_notes'             => 'encrypted:array',
        'vitals'                 => 'array',
        'patient_advice'         => 'array',
        'diagnosis_codes'        => 'array',
        'follow_up_date'         => 'date',
        'abha_linked'            => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this encounter.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the patient for this encounter.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor (staff member) assigned to this encounter.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'doctor_id');
    }

    /**
     * Get the appointment associated with this encounter.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the orders for this encounter.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Modules\Core\Models\Order::class);
    }

    /**
     * Get the bills for this encounter.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Get the insurance transactions for this encounter.
     */
    public function insuranceTransactions(): HasMany
    {
        return $this->hasMany(InsuranceTransaction::class);
    }

    /**
     * Get the conversation associated with this encounter.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Determine if the encounter is still active (not in a terminal state).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Transition the encounter to the given status.
     */
    public function markAs(EncounterStatus $status): self
    {
        $this->status = $status;
        $this->save();

        return $this;
    }
}

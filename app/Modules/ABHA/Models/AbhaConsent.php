<?php

namespace App\Modules\ABHA\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AbhaConsent extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'abha_consents';

    protected $fillable = [
        'id',
        'hospital_id',
        'patient_id',
        'abha_number',
        'granted_to_type',
        'granted_to_id',
        'granted_to_name',
        'purpose',
        'data_scope',
        'status',
        'granted_at',
        'revoked_at',
        'expires_at',
        'revocation_reason',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The entity this consent was granted to (doctor, hospital, lab, etc.).
     */
    public function grantedTo(): MorphTo
    {
        return $this->morphTo('granted_to', 'granted_to_type', 'granted_to_id');
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Check whether this consent is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'granted') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Revoke this consent with a reason.
     */
    public function revoke(string $reason): void
    {
        $this->update([
            'status'            => 'revoked',
            'revoked_at'        => now(),
            'revocation_reason' => $reason,
        ]);
    }
}

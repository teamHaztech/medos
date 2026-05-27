<?php

namespace App\Modules\Patient\Models;

use App\Modules\AIReceptionist\Models\Conversation;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasUuid;
    use BelongsToHospital;
    use Auditable;
    use SoftDeletes;

    protected $table = 'patients';

    protected $fillable = [
        'id',
        'hospital_id',
        'name',
        'phone',
        'phone_verified',
        'email',
        'date_of_birth',
        'age_approximate',
        'gender',
        'blood_group',
        'language_preference',
        'address',
        'city',
        'emergency_contact_name',
        'emergency_contact_phone',
        'medical_history',
        'allergies',
        'current_medications',
        'insurance_details',
        'metadata',
        'created_via',
        'abha_number',
        'abha_address',
        'abha_verified',
        'abha_profile',
    ];

    protected $casts = [
        'date_of_birth'       => 'date',
        'phone_verified'      => 'boolean',
        'insurance_details'   => 'encrypted:array',
        'medical_history'     => 'array',
        'allergies'           => 'array',
        'current_medications' => 'array',
        'metadata'            => 'array',
        'abha_verified'       => 'boolean',
        'abha_profile'        => 'array',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    public function getActiveInsurance(): ?array
    {
        $details = $this->insurance_details;

        if (empty($details)) {
            return null;
        }

        $expiry = $details['expiry_date'] ?? null;
        if ($expiry && now()->greaterThan($expiry)) {
            return null;
        }

        return $details;
    }

    public function getLatestEncounter(): ?Encounter
    {
        return $this->encounters()->latest()->first();
    }

    public function preferredLanguage(): string
    {
        return $this->language_preference ?? 'en';
    }
}

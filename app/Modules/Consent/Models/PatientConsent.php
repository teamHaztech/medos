<?php

namespace App\Modules\Consent\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientConsent extends Model
{
    use HasUuid;

    protected $table = 'patient_consents';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'consent_form_id', 'status',
        'signed_by_name', 'relationship', 'witness_name', 'signed_at', 'notes',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public const STATUSES = ['pending' => 'Pending', 'signed' => 'Signed', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'];

    public const RELATIONSHIPS = ['self' => 'Self', 'guardian' => 'Guardian', 'spouse' => 'Spouse', 'parent' => 'Parent', 'next_of_kin' => 'Next of Kin'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ConsentForm::class, 'consent_form_id');
    }
}

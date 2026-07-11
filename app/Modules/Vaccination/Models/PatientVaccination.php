<?php

namespace App\Modules\Vaccination\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientVaccination extends Model
{
    use HasUuid;

    protected $table = 'patient_vaccinations';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'vaccine_id', 'dose_number',
        'given_date', 'batch_number', 'route', 'site', 'manufacturer', 'expiry_date',
        'given_by_name', 'next_due_date', 'next_dose_done', 'has_aefi', 'aefi_notes', 'notes',
    ];

    protected $casts = [
        'dose_number'    => 'integer',
        'given_date'     => 'date',
        'expiry_date'    => 'date',
        'next_due_date'  => 'date',
        'next_dose_done' => 'boolean',
        'has_aefi'       => 'boolean',
    ];

    public const SITES = [
        'left_thigh'   => 'Left thigh',
        'right_thigh'  => 'Right thigh',
        'left_arm'     => 'Left arm (deltoid)',
        'right_arm'    => 'Right arm (deltoid)',
        'oral'         => 'Oral',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }
}

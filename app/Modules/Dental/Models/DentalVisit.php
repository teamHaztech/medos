<?php

namespace App\Modules\Dental\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalVisit extends Model
{
    use HasUuid;

    protected $table = 'dental_visits';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'visit_date', 'chief_complaint',
        'examination', 'procedures_done', 'advice', 'next_visit_date', 'dentist_name',
    ];

    protected $casts = [
        'visit_date'      => 'date',
        'next_visit_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

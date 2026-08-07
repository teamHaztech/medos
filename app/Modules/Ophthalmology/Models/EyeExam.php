<?php

namespace App\Modules\Ophthalmology\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EyeExam extends Model
{
    use HasUuid;

    protected $table = 'eye_exams';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'exam_date', 'chief_complaint',
        'va_od_unaided', 'va_od_aided', 'va_os_unaided', 'va_os_aided',
        'iop_od', 'iop_os',
        'od_sph', 'od_cyl', 'od_axis', 'od_add',
        'os_sph', 'os_cyl', 'os_axis', 'os_add',
        'pd', 'rx_type',
        'anterior_segment', 'posterior_segment', 'diagnosis', 'advice',
        'next_visit_date', 'examiner_name',
    ];

    protected $casts = [
        'exam_date'       => 'date',
        'next_visit_date' => 'date',
        'iop_od'          => 'decimal:1',
        'iop_os'          => 'decimal:1',
    ];

    public const RX_TYPES = ['glasses' => 'Glasses', 'contact' => 'Contact Lens', 'none' => 'No Correction'];

    /** True when any refraction / spectacle value was entered (drives the Rx print card). */
    public function hasPrescription(): bool
    {
        foreach (['od_sph', 'od_cyl', 'od_axis', 'od_add', 'os_sph', 'os_cyl', 'os_axis', 'os_add'] as $f) {
            if ($this->$f !== null && $this->$f !== '') {
                return true;
            }
        }

        return false;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

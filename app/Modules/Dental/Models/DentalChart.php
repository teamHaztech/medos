<?php

namespace App\Modules\Dental\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DentalChart extends Model
{
    use HasUuid;

    protected $table = 'dental_charts';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'dentition', 'tooth_status', 'notes',
    ];

    protected $casts = [
        'tooth_status' => 'array',
    ];

    /** Tooth status types → label + colour class (used by the chart UI). */
    public const STATUSES = [
        'healthy'         => 'Healthy',
        'caries'          => 'Caries',
        'filled'          => 'Filled',
        'crown'           => 'Crown',
        'root_canal'      => 'Root Canal',
        'implant'         => 'Implant',
        'extract_planned' => 'Extraction Planned',
        'missing'         => 'Missing',
    ];

    public const ADULT_UPPER = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];

    public const ADULT_LOWER = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];

    public const CHILD_UPPER = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];

    public const CHILD_LOWER = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
}

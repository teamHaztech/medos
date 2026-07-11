<?php

namespace App\Modules\Quality\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use HasUuid;

    protected $table = 'incidents';

    protected $fillable = [
        'id', 'hospital_id', 'incident_no', 'reported_by_name', 'occurred_at',
        'department', 'category', 'severity', 'patient_id', 'description',
        'immediate_action', 'capa', 'assigned_to_name', 'status',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'fall'          => 'Patient Fall',
        'medication'    => 'Medication Error',
        'near_miss'     => 'Near Miss',
        'equipment'     => 'Equipment / Device',
        'infection'     => 'Infection Control',
        'security'      => 'Security',
        'documentation' => 'Documentation',
        'other'         => 'Other',
    ];

    public const SEVERITIES = ['minor' => 'Minor', 'moderate' => 'Moderate', 'major' => 'Major', 'sentinel' => 'Sentinel'];

    public const STATUSES = ['reported' => 'Reported', 'under_review' => 'Under Review', 'action_taken' => 'Action Taken', 'closed' => 'Closed'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

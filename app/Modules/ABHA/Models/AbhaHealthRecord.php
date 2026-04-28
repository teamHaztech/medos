<?php

namespace App\Modules\ABHA\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AbhaHealthRecord extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'abha_health_records';

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'abha_number',
        'record_type',
        'source_id',
        'source_type',
        'fhir_data',
        'status',
        'abdm_record_id',
        'linked_at',
        'pushed_at',
    ];

    protected $casts = [
        'fhir_data' => 'array',
        'linked_at' => 'datetime',
        'pushed_at' => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The source record (encounter, prescription, lab result, etc.).
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }
}

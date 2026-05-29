<?php
namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasUuid, BelongsToHospital;

    protected $table = 'orders';

    protected $fillable = [
        'id', 'hospital_id', 'encounter_id', 'patient_id', 'ordered_by',
        'type', 'status', 'items', 'priority', 'results', 'notes', 'completed_at',
        'sample_collected_at', 'sample_collected_by', 'verified_by', 'verified_at',
        // Lab workflow fields (lab_enhancements migration)
        'sample_id', 'sample_type', 'container_type', 'collection_location', 'lab_status',
        'transported_at', 'received_at', 'processing_at', 'result_entered_at',
        'released_at', 'released_by', 'has_critical', 'critical_acknowledged',
        'critical_acknowledged_by', 'critical_acknowledged_at', 'assigned_to',
    ];

    protected $casts = [
        'items' => 'array',
        'results' => 'array',
        'completed_at' => 'datetime',
        'sample_collected_at' => 'datetime',
        'verified_at' => 'datetime',
        'transported_at' => 'datetime',
        'received_at' => 'datetime',
        'processing_at' => 'datetime',
        'result_entered_at' => 'datetime',
        'released_at' => 'datetime',
        'critical_acknowledged_at' => 'datetime',
        'has_critical' => 'boolean',
        'critical_acknowledged' => 'boolean',
    ];

    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'ordered_by'); }
    public function collectedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'sample_collected_by'); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'verified_by'); }
}

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
    ];

    protected $casts = [
        'items' => 'array',
        'results' => 'array',
        'completed_at' => 'datetime',
        'sample_collected_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'ordered_by'); }
    public function collectedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'sample_collected_by'); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'verified_by'); }
}

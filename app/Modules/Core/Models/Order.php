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
        'scheduled_for', 'booking_source',
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
        'scheduled_for' => 'datetime',
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

    /**
     * Lab/imaging/procedure bookings for a hospital on a given date — scheduled
     * for that day, or walk-ins created that day. Newest scheduled first.
     */
    public static function labBookingsForDate(string $hospitalId, string $date)
    {
        return static::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging', 'procedure'])
            ->where(function ($q) use ($date) {
                $q->whereDate('scheduled_for', $date)
                  ->orWhere(function ($w) use ($date) {
                      $w->whereNull('scheduled_for')->whereDate('created_at', $date);
                  });
            })
            ->with(['patient', 'orderedBy'])
            ->orderByRaw('scheduled_for is null')
            ->orderBy('scheduled_for')
            ->orderByDesc('created_at')
            ->get();
    }

    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'ordered_by'); }
    public function collectedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'sample_collected_by'); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'verified_by'); }
}

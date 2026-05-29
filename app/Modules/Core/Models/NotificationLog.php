<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'notifications_log';

    protected $fillable = [
        'id',
        'hospital_id',
        'patient_id',
        'staff_id',
        'channel',
        'type',
        'content',
        'status',
        'external_id',
        'error',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'channel'      => Channel::class,
        'content'      => 'array',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this notification.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the patient this notification was sent to.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the staff member this notification was sent to.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}

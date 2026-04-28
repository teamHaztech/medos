<?php

namespace App\Modules\Appointment\Models;

use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'queue_entries';

    protected $fillable = [
        'hospital_id',
        'appointment_id',
        'patient_id',
        'doctor_id',
        'queue_number',
        'position',
        'status',
        'priority',
        'called_at',
        'seen_at',
        'completed_at',
        'skipped_at',
        'notes',
    ];

    protected $casts = [
        'position'     => 'integer',
        'priority'     => 'integer',
        'called_at'    => 'datetime',
        'seen_at'      => 'datetime',
        'completed_at' => 'datetime',
        'skipped_at'   => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this queue entry.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the appointment for this queue entry.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the patient for this queue entry.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor for this queue entry.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'doctor_id');
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Call the patient (mark as called).
     */
    public function call(): self
    {
        $this->status = 'called';
        $this->called_at = now();
        $this->save();

        return $this;
    }

    /**
     * Skip this queue entry.
     */
    public function skip(): self
    {
        $this->status = 'skipped';
        $this->skipped_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark this queue entry as complete.
     */
    public function complete(): self
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();

        return $this;
    }
}

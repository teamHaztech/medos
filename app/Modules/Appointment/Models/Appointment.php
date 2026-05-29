<?php

namespace App\Modules\Appointment\Models;

use App\Modules\Core\Enums\AppointmentStatus;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'appointments';

    protected $fillable = [
        'id',
        'hospital_id',
        'patient_id',
        'doctor_id',
        'encounter_id',
        'status',
        'slot_start',
        'slot_end',
        'predicted_duration_minutes',
        'actual_duration_minutes',
        'check_in_time',
        'consultation_start_time',
        'consultation_end_time',
        'cancellation_reason',
        'rescheduled_from',
        'booking_source',
        'notes',
        'queue_position',
    ];

    protected $casts = [
        'status'                  => AppointmentStatus::class,
        'slot_start'              => 'datetime',
        'slot_end'                => 'datetime',
        'check_in_time'           => 'datetime',
        'consultation_start_time' => 'datetime',
        'consultation_end_time'   => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this appointment.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the encounter spawned from this appointment.
     */
    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class);
    }

    /**
     * Get the patient for this appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor (staff member) assigned to this appointment.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'doctor_id');
    }

    /**
     * Get the queue entry for this appointment.
     */
    public function queueEntry(): HasOne
    {
        return $this->hasOne(QueueEntry::class);
    }

    /**
     * Get the appointment this one was rescheduled from.
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from');
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Generate a queue token (e.g. "GEN-002") that is unique for a given
     * doctor on a given day. Uses the department prefix + sequence, and
     * increments the sequence until it finds an unused token so two bookings
     * can never collide on the same number.
     */
    public static function generateToken(string $doctorId, ?string $department, \Carbon\Carbon $slotStart): string
    {
        $prefix = strtoupper(substr(str_replace(' ', '', $department ?: 'GEN'), 0, 3));
        $date   = $slotStart->toDateString();

        $base = static::where('doctor_id', $doctorId)->whereDate('slot_start', $date);
        $seq  = (clone $base)->count() + 1;

        do {
            $token = $prefix . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $taken = (clone $base)->where('notes', $token)->exists();
            $seq++;
        } while ($taken);

        return $token;
    }

    /**
     * Mark the patient as checked in.
     */
    public function checkIn(): self
    {
        $this->status = AppointmentStatus::CheckedIn;
        $this->check_in_time = now();
        $this->save();

        return $this;
    }

    /**
     * Start the consultation.
     */
    public function startConsultation(): self
    {
        $this->status = AppointmentStatus::InProgress;
        $this->consultation_start_time = now();
        $this->save();

        return $this;
    }

    /**
     * Complete the appointment.
     */
    public function complete(): self
    {
        $this->status = AppointmentStatus::Completed;
        $this->consultation_end_time = now();
        $this->save();

        return $this;
    }

    /**
     * Calculate the actual consultation duration in minutes.
     */
    public function calculateActualDuration(): ?int
    {
        if (! $this->consultation_start_time || ! $this->consultation_end_time) {
            return null;
        }

        return $this->consultation_start_time->diffInMinutes($this->consultation_end_time);
    }
}

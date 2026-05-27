<?php

namespace App\Modules\Core\Models;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'staff';

    protected $fillable = [
        'id',
        'hospital_id',
        'user_id',
        'name',
        'role',
        'specialty',
        'specialization',
        'department',
        'license_number',
        'phone',
        'email',
        'schedule',
        'performance_metrics',
        'consultation_duration_default',
        'is_active',
    ];

    protected $casts = [
        'role'                          => UserRole::class,
        'schedule'                      => 'array',
        'performance_metrics'           => 'array',
        'consultation_duration_minutes' => 'integer',
        'is_active'                     => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital this staff member belongs to.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the user account linked to this staff member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get all encounters where this staff member is the doctor.
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'doctor_id');
    }

    /**
     * Get all appointments where this staff member is the doctor.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Get the staff member's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Determine if this staff member is a doctor.
     */
    public function isDoctor(): bool
    {
        return $this->role === UserRole::Doctor;
    }

    /**
     * Determine if this staff member is available at the given date and time.
     */
    public function isAvailableAt(Carbon $dateTime): bool
    {
        $schedule = $this->schedule ?? [];
        $dayOfWeek = strtolower($dateTime->format('l'));

        if (! isset($schedule[$dayOfWeek])) {
            return false;
        }

        $daySchedule = $schedule[$dayOfWeek];

        if (empty($daySchedule['start']) || empty($daySchedule['end'])) {
            return false;
        }

        $start = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $daySchedule['start']);
        $end = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $daySchedule['end']);

        return $dateTime->between($start, $end);
    }

    /**
     * Get available time slots for the given date.
     *
     * Returns an array of Carbon instances representing the start of each available slot.
     */
    public function getAvailableSlots(Carbon $date): array
    {
        $schedule = $this->schedule ?? [];
        $dayOfWeek = strtolower($date->format('l'));

        if (! isset($schedule[$dayOfWeek])) {
            return [];
        }

        $daySchedule = $schedule[$dayOfWeek];

        if (empty($daySchedule['start']) || empty($daySchedule['end'])) {
            return [];
        }

        $duration = $this->consultation_duration_minutes ?? 30;
        $start = Carbon::parse($date->format('Y-m-d') . ' ' . $daySchedule['start']);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $daySchedule['end']);

        // Get existing appointments for this date
        $bookedSlots = $this->appointments()
            ->whereDate('slot_start', $date)
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->pluck('slot_start')
            ->map(fn ($dt) => Carbon::parse($dt)->format('H:i'))
            ->toArray();

        $slots = [];
        $current = $start->copy();

        while ($current->copy()->addMinutes($duration)->lte($end)) {
            if (! in_array($current->format('H:i'), $bookedSlots)) {
                $slots[] = $current->copy();
            }
            $current->addMinutes($duration);
        }

        return $slots;
    }
}

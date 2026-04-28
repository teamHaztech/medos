<?php

namespace App\Modules\Core\Enums;

enum AppointmentStatus: string
{
    case Scheduled   = 'scheduled';
    case Confirmed   = 'confirmed';
    case CheckedIn   = 'checked_in';
    case InProgress  = 'in_progress';
    case Completed   = 'completed';
    case NoShow      = 'no_show';
    case Cancelled   = 'cancelled';
    case Rescheduled = 'rescheduled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled   => 'Scheduled',
            self::Confirmed   => 'Confirmed',
            self::CheckedIn   => 'Checked In',
            self::InProgress  => 'In Progress',
            self::Completed   => 'Completed',
            self::NoShow      => 'No Show',
            self::Cancelled   => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
        };
    }

    /**
     * Determine if the appointment is in an active state.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::Confirmed,
            self::CheckedIn,
            self::InProgress,
        ]);
    }
}

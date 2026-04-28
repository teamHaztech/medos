<?php

namespace App\Modules\Core\Enums;

enum EncounterStatus: string
{
    case Initiated      = 'initiated';
    case IntakeComplete = 'intake_complete';
    case Triaged        = 'triaged';
    case Booked         = 'booked';
    case CheckedIn      = 'checked_in';
    case InProgress     = 'in_progress';
    case Completed      = 'completed';
    case Cancelled      = 'cancelled';
    case NoShow         = 'no_show';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initiated      => 'Initiated',
            self::IntakeComplete => 'Intake Complete',
            self::Triaged        => 'Triaged',
            self::Booked         => 'Booked',
            self::CheckedIn      => 'Checked In',
            self::InProgress     => 'In Progress',
            self::Completed      => 'Completed',
            self::Cancelled      => 'Cancelled',
            self::NoShow         => 'No Show',
        };
    }

    /**
     * Determine if the encounter is in an active state.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Initiated,
            self::IntakeComplete,
            self::Triaged,
            self::Booked,
            self::CheckedIn,
            self::InProgress,
        ]);
    }

    /**
     * Determine if the encounter is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::NoShow,
        ]);
    }
}

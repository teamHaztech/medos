<?php

namespace App\Modules\Core\Enums;

enum TriageClassification: string
{
    case Emergency  = 'emergency';
    case Urgent     = 'urgent';
    case SemiUrgent = 'semi_urgent';
    case Routine    = 'routine';

    /**
     * Get a human-readable label for the classification.
     */
    public function label(): string
    {
        return match ($this) {
            self::Emergency  => 'Emergency',
            self::Urgent     => 'Urgent',
            self::SemiUrgent => 'Semi-Urgent',
            self::Routine    => 'Routine',
        };
    }

    /**
     * Get the priority level (lower is more urgent).
     */
    public function priority(): int
    {
        return match ($this) {
            self::Emergency  => 1,
            self::Urgent     => 2,
            self::SemiUrgent => 3,
            self::Routine    => 4,
        };
    }

    /**
     * Get the maximum wait time in minutes for this classification.
     */
    public function maxWaitMinutes(): int
    {
        return match ($this) {
            self::Emergency  => 0,
            self::Urgent     => 15,
            self::SemiUrgent => 30,
            self::Routine    => 60,
        };
    }
}

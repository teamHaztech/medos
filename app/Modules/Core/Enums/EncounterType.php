<?php

namespace App\Modules\Core\Enums;

enum EncounterType: string
{
    case Consultation = 'consultation';
    case FollowUp     = 'follow_up';
    case Emergency    = 'emergency';
    case Procedure    = 'procedure';
    case LabOnly      = 'lab_only';
    case PharmacyOnly = 'pharmacy_only';

    /**
     * Get a human-readable label for the encounter type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Consultation',
            self::FollowUp     => 'Follow-Up',
            self::Emergency    => 'Emergency',
            self::Procedure    => 'Procedure',
            self::LabOnly      => 'Lab Only',
            self::PharmacyOnly => 'Pharmacy Only',
        };
    }

    /**
     * Determine if the encounter type requires a doctor.
     */
    public function requiresDoctor(): bool
    {
        return in_array($this, [
            self::Consultation,
            self::FollowUp,
            self::Emergency,
            self::Procedure,
        ]);
    }
}

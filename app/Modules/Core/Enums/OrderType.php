<?php

namespace App\Modules\Core\Enums;

enum OrderType: string
{
    case Lab       = 'lab';
    case Pharmacy  = 'pharmacy';
    case Imaging   = 'imaging';
    case Procedure = 'procedure';

    /**
     * Get a human-readable label for the order type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lab       => 'Lab',
            self::Pharmacy  => 'Pharmacy',
            self::Imaging   => 'Imaging',
            self::Procedure => 'Procedure',
        };
    }
}

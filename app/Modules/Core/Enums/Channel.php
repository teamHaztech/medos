<?php

namespace App\Modules\Core\Enums;

enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Voice    = 'voice';
    case Kiosk    = 'kiosk';
    case Web      = 'web';
    case WalkIn   = 'walk_in';

    /**
     * Get a human-readable label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Voice    => 'Voice',
            self::Kiosk    => 'Kiosk',
            self::Web      => 'Web',
            self::WalkIn   => 'Walk-In',
        };
    }

    /**
     * Determine if the channel is a digital channel.
     */
    public function isDigital(): bool
    {
        return in_array($this, [
            self::WhatsApp,
            self::Voice,
            self::Web,
        ]);
    }
}

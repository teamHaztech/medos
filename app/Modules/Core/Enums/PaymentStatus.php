<?php

namespace App\Modules\Core\Enums;

enum PaymentStatus: string
{
    case Pending  = 'pending';
    case Partial  = 'partial';
    case Paid     = 'paid';
    case Refunded = 'refunded';
    case Waived   = 'waived';

    /**
     * Get a human-readable label for the payment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Partial  => 'Partial',
            self::Paid     => 'Paid',
            self::Refunded => 'Refunded',
            self::Waived   => 'Waived',
        };
    }

    /**
     * Determine if the payment is settled.
     */
    public function isSettled(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Waived,
        ]);
    }
}

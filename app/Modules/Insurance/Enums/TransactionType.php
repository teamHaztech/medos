<?php

namespace App\Modules\Insurance\Enums;

enum TransactionType: string
{
    case EligibilityCheck = 'eligibility_check';
    case PreAuthorization = 'pre_authorization';
    case ClaimSubmission  = 'claim_submission';
    case ClaimFollowUp    = 'claim_follow_up';

    /**
     * Get a human-readable label for the transaction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EligibilityCheck => 'Eligibility Check',
            self::PreAuthorization => 'Pre-Authorization',
            self::ClaimSubmission  => 'Claim Submission',
            self::ClaimFollowUp    => 'Claim Follow-Up',
        };
    }

    /**
     * Determine if this transaction type requires an encounter.
     */
    public function requiresEncounter(): bool
    {
        return match ($this) {
            self::EligibilityCheck => false,
            self::PreAuthorization => true,
            self::ClaimSubmission  => true,
            self::ClaimFollowUp    => true,
        };
    }
}

<?php

namespace App\Modules\AIReceptionist\Enums;

enum ConversationState: string
{
    case Greeting          = 'greeting';
    case LanguageDetection = 'language_detection';
    case Intake            = 'intake';
    case Triage            = 'triage';
    case InsuranceCheck    = 'insurance_check';
    case Booking           = 'booking';
    case Confirmation      = 'confirmation';
    case Completed         = 'completed';
    case Escalated         = 'escalated';
    case Emergency         = 'emergency';

    /**
     * Get the possible next states from the current state.
     *
     * @return ConversationState[]
     */
    public function next(): array
    {
        return match ($this) {
            self::Greeting          => [self::LanguageDetection, self::Intake, self::Emergency, self::Escalated],
            self::LanguageDetection => [self::Greeting, self::Intake, self::Emergency],
            self::Intake            => [self::Triage, self::Emergency, self::Escalated],
            self::Triage            => [self::InsuranceCheck, self::Booking, self::Emergency, self::Escalated],
            self::InsuranceCheck    => [self::Booking, self::Escalated],
            self::Booking           => [self::Confirmation, self::Escalated],
            self::Confirmation      => [self::Completed],
            self::Completed         => [],
            self::Escalated         => [self::Intake, self::Booking, self::Completed],
            self::Emergency         => [self::Escalated, self::Completed],
        };
    }

    /**
     * Whether this state requires an AI call to process.
     */
    public function requiresAI(): bool
    {
        return match ($this) {
            self::Greeting,
            self::LanguageDetection,
            self::Intake,
            self::Triage,
            self::Booking           => true,

            self::InsuranceCheck,
            self::Confirmation,
            self::Completed,
            self::Escalated,
            self::Emergency         => false,
        };
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Greeting          => 'Greeting',
            self::LanguageDetection => 'Language Detection',
            self::Intake            => 'Intake',
            self::Triage            => 'Triage',
            self::InsuranceCheck    => 'Insurance Check',
            self::Booking           => 'Booking',
            self::Confirmation      => 'Confirmation',
            self::Completed         => 'Completed',
            self::Escalated         => 'Escalated to Staff',
            self::Emergency         => 'Emergency',
        };
    }
}

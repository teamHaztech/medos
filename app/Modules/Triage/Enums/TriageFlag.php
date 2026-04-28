<?php

namespace App\Modules\Triage\Enums;

enum TriageFlag: string
{
    case ChestPain           = 'chest_pain';
    case BreathingDifficulty = 'breathing_difficulty';
    case HighFever           = 'high_fever';
    case Pediatric           = 'pediatric';
    case Pregnancy           = 'pregnancy';
    case Trauma              = 'trauma';
    case Neurological        = 'neurological';
    case SevereBleed         = 'severe_bleed';
    case ChronicExacerbation = 'chronic_exacerbation';
    case MultipleSymptoms    = 'multiple_symptoms';

    public function label(): string
    {
        return match ($this) {
            self::ChestPain           => 'Chest Pain',
            self::BreathingDifficulty => 'Breathing Difficulty',
            self::HighFever           => 'High Fever',
            self::Pediatric           => 'Pediatric Emergency',
            self::Pregnancy           => 'Pregnancy Emergency',
            self::Trauma              => 'Trauma',
            self::Neurological        => 'Neurological Emergency',
            self::SevereBleed         => 'Severe Bleeding',
            self::ChronicExacerbation => 'Chronic Condition Exacerbation',
            self::MultipleSymptoms    => 'Multiple Symptoms',
        };
    }
}

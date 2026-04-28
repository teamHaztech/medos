<?php

namespace App\Modules\Triage\Rules;

use App\Modules\Triage\Enums\TriageFlag;

class RuleEngine
{
    /**
     * Evaluate hard-coded clinical rules against intake data.
     *
     * @return array{triggered: bool, score: float, matched_rules: array, is_emergency: bool, flags: TriageFlag[]}
     */
    public function evaluate(array $intakeData): array
    {
        $matchedRules = [];
        $flags = [];
        $highestScore = 0.0;

        $checks = [
            'checkChestPain',
            'checkBreathingDifficulty',
            'checkHighFeverInfant',
            'checkPregnancyEmergency',
            'checkTrauma',
            'checkSevereBleed',
            'checkNeurological',
            'checkPediatricEmergency',
        ];

        foreach ($checks as $method) {
            $result = $this->{$method}($intakeData);

            if ($result['triggered']) {
                $matchedRules[] = [
                    'rule'      => $result['name'],
                    'score'     => $result['score'],
                    'reasoning' => $result['reasoning'],
                ];
                $flags[] = $result['flag'];
                $highestScore = max($highestScore, $result['score']);
            }
        }

        return [
            'triggered'     => ! empty($matchedRules),
            'score'         => $highestScore,
            'matched_rules' => $matchedRules,
            'is_emergency'  => $highestScore >= config('medos.triage.emergency_threshold', 0.9),
            'flags'         => $flags,
        ];
    }

    // ------------------------------------------------------------------
    // Individual Rule Checks
    // ------------------------------------------------------------------

    protected function checkChestPain(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);
        $age = $data['age'] ?? null;

        $hasChestPain = $this->containsAny($complaint, [
            'chest pain', 'chest tightness', 'seene mein dard', 'alam fi alsadr',
            'chest pressure', 'heart pain',
        ]);

        $triggered = $hasChestPain && $age !== null && $age > 40;

        return [
            'triggered' => $triggered,
            'score'     => 0.95,
            'name'      => 'chest_pain_over_40',
            'reasoning' => 'Chest pain in patient over 40 years — possible cardiac event',
            'flag'      => TriageFlag::ChestPain,
        ];
    }

    protected function checkBreathingDifficulty(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);
        $severity = $this->normalizeSeverity($data);

        $hasBreathing = $this->containsAny($complaint, [
            'breathing difficulty', 'breathless', 'can\'t breathe', 'shortness of breath',
            'saans nahi aa rahi', 'saans ki takleef', 'dyspnea', 'suffocation',
            'difficulty breathing', 'gasping',
        ]);

        $isSevere = $this->containsAny($severity, ['severe', 'critical', 'very bad', 'extreme'])
            || $this->containsAny($complaint, ['severe breathing', 'can\'t breathe', 'gasping', 'suffocation']);

        return [
            'triggered' => $hasBreathing && $isSevere,
            'score'     => 0.95,
            'name'      => 'severe_breathing_difficulty',
            'reasoning' => 'Severe breathing difficulty — possible respiratory emergency',
            'flag'      => TriageFlag::BreathingDifficulty,
        ];
    }

    protected function checkHighFeverInfant(array $data): array
    {
        $tempF = $data['temp_f'] ?? null;
        $age = $data['age'] ?? null;

        $triggered = $tempF !== null && $tempF > 103
            && $age !== null && $age < 2;

        return [
            'triggered' => $triggered,
            'score'     => 0.9,
            'name'      => 'high_fever_infant',
            'reasoning' => 'High fever (>103F) in infant under 2 years — risk of febrile seizure',
            'flag'      => TriageFlag::HighFever,
        ];
    }

    protected function checkPregnancyEmergency(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);
        $severity = $this->normalizeSeverity($data);

        $isPregnant = $this->containsAny($complaint, [
            'pregnant', 'pregnancy', 'garbhvati', 'hamla', 'hamal',
        ]) || ! empty($data['is_pregnant']);

        $hasEmergency = $this->containsAny($complaint, [
            'bleeding', 'severe pain', 'cramps', 'spotting', 'contractions',
            'khoon', 'dard', 'nuzif',
        ]);

        return [
            'triggered' => $isPregnant && $hasEmergency,
            'score'     => 0.95,
            'name'      => 'pregnancy_emergency',
            'reasoning' => 'Pregnant patient with bleeding or severe pain — obstetric emergency',
            'flag'      => TriageFlag::Pregnancy,
        ];
    }

    protected function checkTrauma(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);

        $hasTrauma = $this->containsAny($complaint, [
            'accident', 'fall with injury', 'head injury', 'trauma', 'car crash',
            'road accident', 'hit by', 'collision', 'fracture', 'broken bone',
            'sir mein chot', 'hadsa', 'sadma',
        ]);

        return [
            'triggered' => $hasTrauma,
            'score'     => 0.85,
            'name'      => 'trauma',
            'reasoning' => 'Traumatic injury reported — needs immediate assessment',
            'flag'      => TriageFlag::Trauma,
        ];
    }

    protected function checkSevereBleed(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);
        $severity = $this->normalizeSeverity($data);

        $hasBleed = $this->containsAny($complaint, [
            'heavy bleeding', 'uncontrolled bleeding', 'blood loss',
            'bahut khoon', 'nuzif shadid', 'hemorrhage', 'bleeding heavily',
            'won\'t stop bleeding',
        ]);

        // Also trigger if complaint mentions bleeding and severity is severe
        $bleedingWithSeverity = $this->containsAny($complaint, ['bleeding', 'blood', 'khoon'])
            && $this->containsAny($severity, ['severe', 'heavy', 'uncontrolled', 'critical']);

        return [
            'triggered' => $hasBleed || $bleedingWithSeverity,
            'score'     => 0.9,
            'name'      => 'severe_bleed',
            'reasoning' => 'Heavy or uncontrolled bleeding — hemorrhagic risk',
            'flag'      => TriageFlag::SevereBleed,
        ];
    }

    protected function checkNeurological(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);

        $hasNeuro = $this->containsAny($complaint, [
            'sudden weakness', 'slurred speech', 'seizure', 'convulsion',
            'facial drooping', 'numbness one side', 'stroke', 'fit',
            'loss of consciousness', 'unresponsive', 'behoshi', 'daura',
            'lakwa', 'paralysis', 'sudden confusion',
        ]);

        return [
            'triggered' => $hasNeuro,
            'score'     => 0.95,
            'name'      => 'neurological_emergency',
            'reasoning' => 'Neurological emergency signs — possible stroke or seizure',
            'flag'      => TriageFlag::Neurological,
        ];
    }

    protected function checkPediatricEmergency(array $data): array
    {
        $complaint = $this->normalizeComplaint($data);
        $age = $data['age'] ?? null;
        $tempF = $data['temp_f'] ?? null;

        $isChild = $age !== null && $age < 14;

        $hasLethargy = $this->containsAny($complaint, [
            'lethargy', 'lethargic', 'not responding', 'drowsy', 'unresponsive',
            'very weak', 'floppy', 'susti',
        ]);

        $hasHighFever = ($tempF !== null && $tempF > 102)
            || $this->containsAny($complaint, ['high fever', 'tez bukhar']);

        return [
            'triggered' => $isChild && $hasLethargy && $hasHighFever,
            'score'     => 0.85,
            'name'      => 'pediatric_emergency',
            'reasoning' => 'Pediatric patient with lethargy and high fever — urgent assessment needed',
            'flag'      => TriageFlag::Pediatric,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function normalizeComplaint(array $data): string
    {
        $parts = [];

        if (! empty($data['chief_complaint'])) {
            $parts[] = $data['chief_complaint'];
        }
        if (! empty($data['symptoms']) && is_array($data['symptoms'])) {
            $parts[] = implode(' ', $data['symptoms']);
        }

        return strtolower(implode(' ', $parts));
    }

    protected function normalizeSeverity(array $data): string
    {
        $parts = [];

        if (! empty($data['severity'])) {
            $parts[] = $data['severity'];
        }
        if (! empty($data['severity_indicators']) && is_array($data['severity_indicators'])) {
            $parts[] = implode(' ', $data['severity_indicators']);
        }

        return strtolower(implode(' ', $parts));
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Modules\Triage\Rules;

class ClinicalScorer
{
    /**
     * Mapping of common complaints to base severity scores (0.0 - 0.7).
     * Covers 50+ complaints including transliterated Hindi/Arabic terms.
     */
    public const COMPLAINT_SEVERITY_MAP = [
        // High severity (0.6 - 0.8)
        'chest pain'           => 0.8,
        'seene mein dard'      => 0.8,
        'alam fi alsadr'       => 0.8,
        'breathing difficulty' => 0.7,
        'shortness of breath'  => 0.7,
        'saans ki takleef'     => 0.7,
        'heart palpitations'   => 0.65,
        'severe abdominal pain'=> 0.7,
        'blood in stool'       => 0.65,
        'blood in urine'       => 0.65,
        'fainting'             => 0.7,
        'behoshi'              => 0.7,
        'seizure'              => 0.8,
        'daura'                => 0.8,
        'stroke symptoms'      => 0.8,
        'severe headache'      => 0.6,
        'high blood pressure'  => 0.6,
        'allergic reaction'    => 0.65,
        'poisoning'            => 0.8,
        'burns'                => 0.65,

        // Medium severity (0.3 - 0.6)
        'fever'                => 0.3,
        'bukhar'               => 0.3,
        'humma'                => 0.3,
        'cough'                => 0.25,
        'khansi'               => 0.25,
        'cold'                 => 0.2,
        'zukam'                => 0.2,
        'headache'             => 0.3,
        'sir dard'             => 0.3,
        'sudaa'                => 0.3,
        'stomach pain'         => 0.4,
        'pet dard'             => 0.4,
        'alam fi albatn'       => 0.4,
        'vomiting'             => 0.4,
        'ulti'                 => 0.4,
        'diarrhea'             => 0.35,
        'dast'                 => 0.35,
        'back pain'            => 0.35,
        'kamar dard'           => 0.35,
        'body pain'            => 0.3,
        'badan dard'           => 0.3,
        'joint pain'           => 0.35,
        'jodon ka dard'        => 0.35,
        'dizziness'            => 0.4,
        'chakkar'              => 0.4,
        'nausea'               => 0.3,
        'sore throat'          => 0.2,
        'gale mein dard'       => 0.2,
        'ear pain'             => 0.25,
        'kaan dard'            => 0.25,
        'eye pain'             => 0.3,
        'tooth pain'           => 0.3,
        'daant dard'           => 0.3,
        'urinary problem'      => 0.35,
        'peshab ki takleef'    => 0.35,
        'skin rash'            => 0.2,
        'daane'                => 0.2,
        'swelling'             => 0.35,
        'sujan'                => 0.35,
        'wound'                => 0.3,
        'zakhm'                => 0.3,
        'pregnancy checkup'    => 0.25,
        'periods problem'      => 0.3,
        'weight loss'          => 0.3,
        'fatigue'              => 0.25,
        'thakan'               => 0.25,
        'insomnia'             => 0.2,
        'neend nahi aati'      => 0.2,
        'anxiety'              => 0.3,
        'depression'           => 0.35,
        'diabetes checkup'     => 0.25,
        'blood pressure check' => 0.2,
        'general checkup'      => 0.1,
        'follow up'            => 0.1,
        'vaccination'          => 0.1,
    ];

    /**
     * Score intake data using ESI-adapted clinical scoring.
     *
     * @return array{score: float, factors: array}
     */
    public function score(array $intakeData, ?array $patientHistory = null): array
    {
        $factors = [];
        $totalScore = 0.0;

        // 1. Base complaint severity
        $complaintScore = $this->scoreComplaint($intakeData);
        $factors[] = ['factor' => 'complaint_severity', 'value' => $complaintScore, 'detail' => $intakeData['chief_complaint'] ?? 'unknown'];
        $totalScore += $complaintScore;

        // 2. Duration factor: symptoms > 7 days without improvement
        $durationDays = $intakeData['duration_days'] ?? null;
        $improving = $intakeData['improving'] ?? null;
        if ($durationDays !== null && $durationDays > 7 && $improving !== true) {
            $factors[] = ['factor' => 'prolonged_duration', 'value' => 0.1, 'detail' => "{$durationDays} days without improvement"];
            $totalScore += 0.1;
        }

        // 3. Age factor: < 5 or > 65
        $age = $intakeData['age'] ?? null;
        if ($age !== null && ($age < 5 || $age > 65)) {
            $factors[] = ['factor' => 'age_risk', 'value' => 0.1, 'detail' => "Age {$age}"];
            $totalScore += 0.1;
        }

        // 4. Vital signs — fever
        $tempF = $intakeData['temp_f'] ?? null;
        if ($tempF !== null) {
            if ($tempF > 103) {
                $factors[] = ['factor' => 'high_fever', 'value' => 0.2, 'detail' => "Temp {$tempF}F"];
                $totalScore += 0.2;
            } elseif ($tempF > 101) {
                $factors[] = ['factor' => 'moderate_fever', 'value' => 0.1, 'detail' => "Temp {$tempF}F"];
                $totalScore += 0.1;
            }
        }

        // 5. Multiple symptoms bonus
        $symptoms = $intakeData['symptoms'] ?? [];
        if (is_array($symptoms) && count($symptoms) > 1) {
            $extra = (count($symptoms) - 1) * 0.05;
            $factors[] = ['factor' => 'multiple_symptoms', 'value' => $extra, 'detail' => count($symptoms) . ' symptoms'];
            $totalScore += $extra;
        }

        // 6. Chronic condition exacerbation
        if ($patientHistory !== null) {
            $chronicBonus = $this->scoreChronicRelevance($intakeData, $patientHistory);
            if ($chronicBonus > 0) {
                $factors[] = ['factor' => 'chronic_exacerbation', 'value' => $chronicBonus, 'detail' => 'Relevant chronic condition'];
                $totalScore += $chronicBonus;
            }
        }

        // Cap at 0.89 — only rule engine can push to emergency
        $totalScore = min(0.89, max(0.0, $totalScore));

        return [
            'score'   => round($totalScore, 3),
            'factors' => $factors,
        ];
    }

    /**
     * Score the chief complaint against the severity map.
     */
    protected function scoreComplaint(array $data): float
    {
        $complaint = strtolower(trim($data['chief_complaint'] ?? ''));

        if (empty($complaint)) {
            return 0.1;
        }

        // Try exact match first
        if (isset(self::COMPLAINT_SEVERITY_MAP[$complaint])) {
            return self::COMPLAINT_SEVERITY_MAP[$complaint];
        }

        // Try partial / keyword match — find best matching entry
        $bestScore = 0.1;
        foreach (self::COMPLAINT_SEVERITY_MAP as $key => $score) {
            if (str_contains($complaint, $key) || str_contains($key, $complaint)) {
                $bestScore = max($bestScore, $score);
            }
        }

        return $bestScore;
    }

    /**
     * Check if any chronic conditions in history are relevant to the current complaint.
     */
    protected function scoreChronicRelevance(array $intakeData, array $history): float
    {
        $complaint = strtolower($intakeData['chief_complaint'] ?? '');
        $chronicConditions = array_map('strtolower', $history['chronic_conditions'] ?? []);

        if (empty($chronicConditions) || empty($complaint)) {
            return 0.0;
        }

        $relevanceMap = [
            'diabetes'     => ['sugar', 'fatigue', 'wound', 'vision', 'numbness', 'thirst', 'weight loss'],
            'hypertension' => ['headache', 'chest pain', 'dizziness', 'blurred vision', 'blood pressure'],
            'asthma'       => ['breathing', 'cough', 'wheeze', 'chest tightness', 'saans'],
            'heart'        => ['chest pain', 'breathless', 'palpitation', 'swelling', 'fatigue'],
            'copd'         => ['breathing', 'cough', 'sputum', 'wheeze'],
            'kidney'       => ['urinary', 'swelling', 'fatigue', 'nausea', 'blood pressure'],
            'liver'        => ['abdominal pain', 'jaundice', 'nausea', 'fatigue', 'swelling'],
            'arthritis'    => ['joint pain', 'swelling', 'stiffness', 'jodon'],
            'epilepsy'     => ['seizure', 'fit', 'convulsion', 'daura'],
            'thyroid'      => ['fatigue', 'weight', 'hair loss', 'palpitation'],
        ];

        foreach ($chronicConditions as $condition) {
            foreach ($relevanceMap as $chronicKey => $relatedSymptoms) {
                if (str_contains($condition, $chronicKey)) {
                    foreach ($relatedSymptoms as $symptom) {
                        if (str_contains($complaint, $symptom)) {
                            return 0.1;
                        }
                    }
                }
            }
        }

        return 0.0;
    }
}

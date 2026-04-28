<?php

namespace App\Modules\Triage\Services;

use App\Modules\Core\Enums\TriageClassification;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Patient\Models\Patient;
use App\Modules\Triage\Enums\TriageFlag;
use App\Modules\Triage\Rules\ClinicalScorer;
use App\Modules\Triage\Rules\RuleEngine;
use Illuminate\Support\Facades\Http;

class TriageService extends BaseModuleService
{
    protected RuleEngine $ruleEngine;
    protected ClinicalScorer $clinicalScorer;
    protected SpecialtyMapper $specialtyMapper;

    public function __construct(
        HospitalContext $hospitalContext,
        RuleEngine $ruleEngine,
        ClinicalScorer $clinicalScorer,
        SpecialtyMapper $specialtyMapper,
    ) {
        parent::__construct($hospitalContext);
        $this->ruleEngine = $ruleEngine;
        $this->clinicalScorer = $clinicalScorer;
        $this->specialtyMapper = $specialtyMapper;
    }

    /**
     * Perform a full triage assessment on intake data.
     *
     * @param  array       $intakeData  Keys: chief_complaint, symptoms, severity, severity_indicators, age, temp_f, duration_days, improving, is_pregnant, etc.
     * @param  Patient|null $patient    Optional patient for history-based scoring.
     * @return array{score: float, classification: TriageClassification, reasoning: array, suggested_specialty: string, flags: TriageFlag[]}
     */
    public function assess(array $intakeData, ?Patient $patient = null): array
    {
        $reasoning = [];
        $flags = [];

        // ---------------------------------------------------------------
        // 1. Run Rule Engine (hard rules)
        // ---------------------------------------------------------------
        $ruleResult = $this->ruleEngine->evaluate($intakeData);
        $reasoning[] = [
            'stage'   => 'rule_engine',
            'result'  => $ruleResult['triggered'] ? 'triggered' : 'no_match',
            'details' => $ruleResult['matched_rules'],
        ];
        $flags = array_merge($flags, $ruleResult['flags']);

        // ---------------------------------------------------------------
        // 2. Run Clinical Scorer (ESI-based)
        // ---------------------------------------------------------------
        $patientHistory = null;
        if ($patient) {
            $patientHistory = [
                'chronic_conditions' => $patient->medical_history['chronic_conditions'] ?? [],
                'allergies'          => is_array($patient->allergies) ? $patient->allergies : [],
            ];
        }

        $clinicalResult = $this->clinicalScorer->score($intakeData, $patientHistory);
        $reasoning[] = [
            'stage'   => 'clinical_scorer',
            'score'   => $clinicalResult['score'],
            'factors' => $clinicalResult['factors'],
        ];

        // Check for multiple symptoms flag
        $symptoms = $intakeData['symptoms'] ?? [];
        if (is_array($symptoms) && count($symptoms) > 2) {
            $flags[] = TriageFlag::MultipleSymptoms;
        }

        // Check chronic exacerbation flag
        if ($patientHistory && $this->hasChronicExacerbation($clinicalResult)) {
            $flags[] = TriageFlag::ChronicExacerbation;
        }

        // ---------------------------------------------------------------
        // 3. Optionally run ML scorer
        // ---------------------------------------------------------------
        $mlScore = null;
        if (config('medos.triage.ml_enabled', false) && config('medos.triage.ml_endpoint')) {
            $mlScore = $this->runMlScorer($intakeData);
            if ($mlScore !== null) {
                $reasoning[] = [
                    'stage' => 'ml_scorer',
                    'score' => $mlScore,
                ];
            }
        }

        // ---------------------------------------------------------------
        // 4. Combine scores
        // ---------------------------------------------------------------
        $finalScore = $this->combineScores(
            ruleResult: $ruleResult,
            clinicalScore: $clinicalResult['score'],
            mlScore: $mlScore,
        );

        // ---------------------------------------------------------------
        // 5. Classify
        // ---------------------------------------------------------------
        $classification = $this->classifyScore($finalScore);

        // ---------------------------------------------------------------
        // 6. Suggested specialty
        // ---------------------------------------------------------------
        $suggestedSpecialty = $this->specialtyMapper->suggest(
            $intakeData['chief_complaint'] ?? '',
            $intakeData['age'] ?? null,
            $intakeData['gender'] ?? null,
        );

        $this->logInfo('Triage assessment completed', [
            'score'          => $finalScore,
            'classification' => $classification->value,
            'specialty'      => $suggestedSpecialty,
            'flags'          => array_map(fn (TriageFlag $f) => $f->value, $flags),
        ]);

        return [
            'score'               => round($finalScore, 3),
            'classification'      => $classification,
            'reasoning'           => $reasoning,
            'suggested_specialty' => $suggestedSpecialty,
            'flags'               => array_unique($flags),
        ];
    }

    /**
     * Map a numeric triage score to a TriageClassification enum.
     */
    public function classifyScore(float $score): TriageClassification
    {
        $thresholds = [
            'emergency'   => config('medos.triage.emergency_threshold', 0.9),
            'urgent'      => config('medos.triage.urgent_threshold', 0.7),
            'semi_urgent' => config('medos.triage.semi_urgent_threshold', 0.4),
        ];

        if ($score >= $thresholds['emergency']) {
            return TriageClassification::Emergency;
        }

        if ($score >= $thresholds['urgent']) {
            return TriageClassification::Urgent;
        }

        if ($score >= $thresholds['semi_urgent']) {
            return TriageClassification::SemiUrgent;
        }

        return TriageClassification::Routine;
    }

    /**
     * Combine scores from rule engine, clinical scorer, and ML.
     * Rules always win if they trigger emergency. ML can only increase, never decrease.
     */
    protected function combineScores(array $ruleResult, float $clinicalScore, ?float $mlScore): float
    {
        // If rules triggered an emergency, use rule score directly
        if ($ruleResult['is_emergency']) {
            return $ruleResult['score'];
        }

        // Start with clinical score
        $combined = $clinicalScore;

        // If rules triggered but not emergency, take the higher of rule vs clinical
        if ($ruleResult['triggered']) {
            $combined = max($combined, $ruleResult['score']);
        }

        // ML can only increase the score, never decrease
        if ($mlScore !== null && $mlScore > $combined) {
            $combined = $mlScore;
        }

        return min(1.0, $combined);
    }

    /**
     * Call external ML scoring endpoint.
     */
    protected function runMlScorer(array $intakeData): ?float
    {
        try {
            $response = Http::timeout(5)->post(config('medos.triage.ml_endpoint'), [
                'intake_data' => $intakeData,
            ]);

            if ($response->successful()) {
                $score = $response->json('score');
                return is_numeric($score) ? (float) $score : null;
            }
        } catch (\Throwable $e) {
            $this->logWarning('ML triage scorer failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Check if clinical scoring factors include a chronic exacerbation.
     */
    protected function hasChronicExacerbation(array $clinicalResult): bool
    {
        foreach ($clinicalResult['factors'] as $factor) {
            if (($factor['factor'] ?? '') === 'chronic_exacerbation' && $factor['value'] > 0) {
                return true;
            }
        }

        return false;
    }
}

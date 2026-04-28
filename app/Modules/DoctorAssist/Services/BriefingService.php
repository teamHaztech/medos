<?php

namespace App\Modules\DoctorAssist\Services;

use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;

class BriefingService extends BaseModuleService
{
    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    // ---------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------

    /**
     * Generate a pre-consultation briefing from the patient graph.
     *
     * Returns a structured array:
     *   1. Patient demographics + language preference
     *   2. Current complaint (from intake_data)
     *   3. Triage score and classification
     *   4. Previous encounters (last 5) with diagnoses
     *   5. Known allergies and current medications
     *   6. Insurance status and coverage context
     *
     * @param  Encounter  $encounter  The current encounter.
     * @return array                  Structured briefing data.
     */
    public function generateBriefing(Encounter $encounter): array
    {
        $encounter->loadMissing(['patient', 'doctor', 'appointment', 'conversation']);
        $patient = $encounter->patient;

        return [
            'encounter_id' => $encounter->id,
            'generated_at' => now()->toIso8601String(),
            'demographics' => $this->buildDemographics($patient),
            'current_complaint' => $this->buildCurrentComplaint($encounter),
            'triage' => $this->buildTriageInfo($encounter),
            'previous_encounters' => $this->buildPreviousEncounters($patient, $encounter->id),
            'allergies_and_medications' => $this->buildAllergiesAndMedications($patient),
            'insurance' => $this->buildInsuranceContext($patient),
        ];
    }

    // ---------------------------------------------------------------
    // Private Builders
    // ---------------------------------------------------------------

    /**
     * Build patient demographics section.
     */
    private function buildDemographics(Patient $patient): array
    {
        return [
            'patient_id'         => $patient->id,
            'name'               => $patient->full_name,
            'age'                => $patient->date_of_birth ? $patient->date_of_birth->age : null,
            'date_of_birth'      => $patient->date_of_birth?->toDateString(),
            'gender'             => $patient->gender,
            'phone'              => $patient->phone,
            'blood_type'         => $patient->blood_type,
            'preferred_language' => $patient->preferred_language ?? 'en',
            'emergency_contact'  => [
                'name'  => $patient->emergency_contact_name,
                'phone' => $patient->emergency_contact_phone,
            ],
        ];
    }

    /**
     * Build current complaint section from intake data.
     */
    private function buildCurrentComplaint(Encounter $encounter): array
    {
        $intakeData = $encounter->intake_data ?? [];

        return [
            'chief_complaint'  => $encounter->chief_complaint ?? $intakeData['chief_complaint'] ?? null,
            'symptoms'         => $intakeData['symptoms'] ?? [],
            'duration'         => $intakeData['duration'] ?? null,
            'severity'         => $intakeData['severity'] ?? null,
            'onset'            => $intakeData['onset'] ?? null,
            'associated_symptoms' => $intakeData['associated_symptoms'] ?? [],
            'channel'          => $encounter->channel,
            'type'             => $encounter->type?->value,
        ];
    }

    /**
     * Build triage information section.
     */
    private function buildTriageInfo(Encounter $encounter): array
    {
        return [
            'classification' => $encounter->triage_classification?->value,
            'label'          => $encounter->triage_classification?->label() ?? 'Not triaged',
            'reasoning'      => $encounter->triage_reasoning,
        ];
    }

    /**
     * Build previous encounters section (last 5, excluding current).
     */
    private function buildPreviousEncounters(Patient $patient, string $currentEncounterId): array
    {
        $encounters = $patient->encounters()
            ->where('id', '!=', $currentEncounterId)
            ->with(['doctor'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $encounters->map(function (Encounter $enc) {
            return [
                'id'                     => $enc->id,
                'date'                   => $enc->created_at->toDateString(),
                'type'                   => $enc->type?->value,
                'status'                 => $enc->status->value,
                'doctor'                 => $enc->doctor?->full_name,
                'chief_complaint'        => $enc->chief_complaint,
                'diagnosis_codes'        => $enc->diagnosis_codes,
                'summary'                => $enc->summary,
                'triage_classification'  => $enc->triage_classification?->value,
            ];
        })->toArray();
    }

    /**
     * Build allergies and medications section.
     */
    private function buildAllergiesAndMedications(Patient $patient): array
    {
        return [
            'allergies'           => $patient->allergies ?? [],
            'current_medications' => $patient->current_medications ?? [],
            'has_allergies'       => ! empty($patient->allergies),
            'allergy_count'       => count($patient->allergies ?? []),
            'medication_count'    => count($patient->current_medications ?? []),
        ];
    }

    /**
     * Build insurance status and coverage context.
     */
    private function buildInsuranceContext(Patient $patient): array
    {
        $insurance = $patient->getActiveInsurance();

        return [
            'has_active_insurance' => $insurance !== null,
            'provider'             => $patient->insurance_provider,
            'policy_number'        => $patient->insurance_policy_number,
            'coverage_details'     => $insurance ? [
                'plan_name'    => $insurance['plan_name'] ?? null,
                'coverage_type' => $insurance['coverage_type'] ?? null,
                'co_pay'       => $insurance['co_pay'] ?? null,
                'expiry_date'  => $insurance['expiry_date'] ?? null,
            ] : null,
        ];
    }
}

<?php

namespace App\Modules\Insurance\Services;

use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Insurance\Enums\InsuranceProvider;
use App\Modules\Insurance\Enums\TransactionType;
use App\Modules\Insurance\Models\InsuranceTransaction;
use App\Modules\Insurance\Providers\BaseInsuranceProvider;
use App\Modules\Insurance\Providers\IndiaTPAProvider;
use App\Modules\Insurance\Providers\UAEDHAProvider;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Facades\Cache;

class InsuranceService extends BaseModuleService
{
    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    // ---------------------------------------------------------------
    // Eligibility
    // ---------------------------------------------------------------

    /**
     * Verify insurance eligibility for a patient.
     *
     * 1. Get insurance details from patient record
     * 2. Check cache (Redis, configurable TTL from config)
     * 3. If not cached, call provider-specific verification
     * 4. Log as InsuranceTransaction (type: eligibility_check)
     * 5. Return structured eligibility result
     */
    public function verifyEligibility(Patient $patient, ?string $insurerCode = null): array
    {
        $hospitalId = $this->requireHospital();

        // 1. Get insurance details from patient record
        $insuranceDetails = $patient->insurance_details ?? [];
        $insurerCode = $insurerCode ?? ($insuranceDetails['insurer_code'] ?? null);

        if (empty($insurerCode) || empty($insuranceDetails)) {
            $this->logWarning('No insurance details found for patient', [
                'patient_id' => $patient->id,
            ]);

            return [
                'eligible'         => false,
                'plan_name'        => null,
                'copay_percent'    => 0,
                'network_type'     => 'unknown',
                'benefits'         => [],
                'requires_preauth' => false,
                'cached'           => false,
                'message'          => 'No insurance details on file.',
            ];
        }

        // 2. Check cache
        $cacheTtl = config('medos.insurance.eligibility_cache_hours', 24) * 3600;
        $cacheKey = "insurance:eligibility:{$hospitalId}:{$patient->id}:{$insurerCode}";

        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            $this->logInfo('Returning cached eligibility result', [
                'patient_id' => $patient->id,
            ]);

            $cachedResult['cached'] = true;
            return $cachedResult;
        }

        // 3. Call provider-specific verification
        $provider = $this->resolveProvider($insurerCode);
        $result = $provider->verifyEligibility($insuranceDetails);

        // Normalize result
        $eligibilityResult = [
            'eligible'         => $result['eligible'] ?? false,
            'plan_name'        => $result['plan_name'] ?? null,
            'copay_percent'    => $result['copay_percent'] ?? 0,
            'network_type'     => $result['network_type'] ?? 'unknown',
            'benefits'         => $result['benefits'] ?? [],
            'requires_preauth' => $result['requires_preauth'] ?? false,
            'cached'           => false,
        ];

        // Cache the result
        Cache::put($cacheKey, $eligibilityResult, $cacheTtl);

        // 4. Log as InsuranceTransaction
        InsuranceTransaction::create([
            'hospital_id'      => $hospitalId,
            'patient_id'       => $patient->id,
            'provider_name'    => $insurerCode,
            'policy_number'    => $insuranceDetails['policy_number'] ?? null,
            'transaction_type' => TransactionType::EligibilityCheck->value,
            'status'           => $eligibilityResult['eligible'] ? 'approved' : 'denied',
            'request_payload'  => $insuranceDetails,
            'response_payload' => $result,
            'submitted_at'     => now(),
            'responded_at'     => now(),
        ]);

        $this->logInfo('Eligibility verified', [
            'patient_id' => $patient->id,
            'eligible'   => $eligibilityResult['eligible'],
        ]);

        return $eligibilityResult;
    }

    // ---------------------------------------------------------------
    // Pre-Authorization
    // ---------------------------------------------------------------

    /**
     * Check whether pre-authorization is required for given procedure codes.
     */
    public function checkPreAuthRequired(string $insurerCode, array $procedureCodes): bool
    {
        // TODO: Implement actual pre-auth requirement lookup per insurer
        // Some insurers maintain lists of procedures that always require pre-auth
        // For now, check if the insurer generally requires pre-auth for any procedure

        $alwaysRequirePreAuth = ['pmjay', 'mediassist', 'paramount', 'vidal_health'];

        if (in_array($insurerCode, $alwaysRequirePreAuth)) {
            return true;
        }

        // High-cost procedures typically require pre-auth
        $highCostPrefixes = ['99223', '99232', '27447', '33533', '47562'];
        foreach ($procedureCodes as $code) {
            foreach ($highCostPrefixes as $prefix) {
                if (str_starts_with($code, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Submit a pre-authorization request for an encounter.
     */
    public function submitPreAuth(
        Encounter $encounter,
        array $diagnosisCodes,
        array $procedureCodes,
    ): InsuranceTransaction {
        $hospitalId = $this->requireHospital();
        $patient = $encounter->patient;
        $insuranceDetails = $patient->insurance_details ?? [];
        $insurerCode = $insuranceDetails['insurer_code'] ?? 'unknown';

        $provider = $this->resolveProvider($insurerCode);

        $data = [
            'encounter_id'    => $encounter->id,
            'patient_id'      => $patient->id,
            'member_id'       => $insuranceDetails['member_id'] ?? '',
            'insurer_code'    => $insurerCode,
            'diagnosis_codes' => $diagnosisCodes,
            'procedure_codes' => $procedureCodes,
            'estimated_cost'  => $this->estimateProcedureCost($procedureCodes),
            'encounter_type'  => $encounter->type ?? 'outpatient',
        ];

        $response = $provider->submitPreAuth($data);

        $transaction = InsuranceTransaction::create([
            'hospital_id'          => $hospitalId,
            'encounter_id'         => $encounter->id,
            'patient_id'           => $patient->id,
            'provider_name'        => $insurerCode,
            'policy_number'        => $insuranceDetails['policy_number'] ?? null,
            'transaction_type'     => TransactionType::PreAuthorization->value,
            'status'               => $response['status'] ?? 'pending',
            'requested_amount'     => $data['estimated_cost'],
            'approved_amount'      => $response['approved_amount'] ?? 0,
            'authorization_number' => $response['reference_id'] ?? null,
            'diagnosis_codes'      => $diagnosisCodes,
            'procedure_codes'      => $procedureCodes,
            'request_payload'      => $data,
            'response_payload'     => $response,
            'submitted_at'         => now(),
            'responded_at'         => isset($response['status']) && $response['status'] !== 'pending_review' ? now() : null,
        ]);

        $this->logInfo('Pre-auth submitted', [
            'transaction_id' => $transaction->id,
            'encounter_id'   => $encounter->id,
            'status'         => $response['status'] ?? 'pending',
        ]);

        return $transaction;
    }

    // ---------------------------------------------------------------
    // Claims
    // ---------------------------------------------------------------

    /**
     * Submit a claim for an encounter, auto-assembling from encounter data.
     */
    public function submitClaim(Encounter $encounter): InsuranceTransaction
    {
        $hospitalId = $this->requireHospital();
        $patient = $encounter->patient;
        $insuranceDetails = $patient->insurance_details ?? [];
        $insurerCode = $insuranceDetails['insurer_code'] ?? 'unknown';

        $provider = $this->resolveProvider($insurerCode);

        // Auto-assemble claim data from the encounter
        $diagnosisCodes = $encounter->diagnosis_codes ?? [];
        $procedureCodes = $encounter->procedure_codes ?? [];

        // Get associated bill if available
        $bill = $encounter->bill ?? null;
        $totalAmount = $bill ? (float) $bill->total_amount : 0;
        $lineItems = $bill ? ($bill->line_items ?? []) : [];

        // Check for existing pre-auth
        $preAuth = InsuranceTransaction::where('encounter_id', $encounter->id)
            ->where('type', TransactionType::PreAuthorization->value)
            ->where('status', 'approved')
            ->first();

        $data = [
            'encounter_id'       => $encounter->id,
            'patient_id'         => $patient->id,
            'member_id'          => $insuranceDetails['member_id'] ?? '',
            'insurer_code'       => $insurerCode,
            'diagnosis_codes'    => $diagnosisCodes,
            'procedure_codes'    => $procedureCodes,
            'total_amount'       => $totalAmount,
            'line_items'         => $lineItems,
            'encounter_start'    => $encounter->created_at?->toDateString(),
            'encounter_end'      => $encounter->updated_at?->toDateString(),
            'pre_auth_reference' => $preAuth?->authorization_number,
        ];

        $response = $provider->submitClaim($data);

        $transaction = InsuranceTransaction::create([
            'hospital_id'          => $hospitalId,
            'encounter_id'         => $encounter->id,
            'patient_id'           => $patient->id,
            'provider_name'        => $insurerCode,
            'policy_number'        => $insuranceDetails['policy_number'] ?? null,
            'transaction_type'     => TransactionType::ClaimSubmission->value,
            'status'               => $response['status'] ?? 'submitted',
            'requested_amount'     => $totalAmount,
            'authorization_number' => $response['reference_id'] ?? null,
            'diagnosis_codes'      => $diagnosisCodes,
            'procedure_codes'      => $procedureCodes,
            'request_payload'      => $data,
            'response_payload'     => $response,
            'submitted_at'         => now(),
        ]);

        $this->logInfo('Claim submitted', [
            'transaction_id' => $transaction->id,
            'encounter_id'   => $encounter->id,
            'amount'         => $totalAmount,
        ]);

        return $transaction;
    }

    /**
     * Check the status of a previously submitted claim/transaction.
     */
    public function checkClaimStatus(InsuranceTransaction $transaction): array
    {
        $insurerCode = $transaction->provider_name;
        $referenceId = $transaction->authorization_number;

        if (empty($referenceId)) {
            return [
                'status'        => $transaction->status,
                'message'       => 'No provider reference ID available for status check.',
                'updated'       => false,
            ];
        }

        $provider = $this->resolveProvider($insurerCode);
        $result = $provider->checkStatus($referenceId);

        // Update the transaction if status changed
        $updated = false;
        if (isset($result['status']) && $result['status'] !== $transaction->status) {
            $transaction->status = $result['status'];
            $transaction->response_payload = $result;
            $transaction->responded_at = now();

            if (isset($result['approved_amount'])) {
                $transaction->approved_amount = $result['approved_amount'];
            }
            if (isset($result['denial_reason'])) {
                $transaction->denial_reason = $result['denial_reason'];
            }

            $transaction->save();
            $updated = true;

            // Log follow-up
            InsuranceTransaction::create([
                'hospital_id'      => $transaction->hospital_id,
                'encounter_id'     => $transaction->encounter_id,
                'patient_id'       => $transaction->patient_id,
                'provider_name'    => $insurerCode,
                'policy_number'    => $transaction->policy_number,
                'transaction_type' => TransactionType::ClaimFollowUp->value,
                'status'           => $result['status'],
                'request_payload'  => ['reference_id' => $referenceId],
                'response_payload' => $result,
                'submitted_at'     => now(),
                'responded_at'     => now(),
            ]);
        }

        return [
            'status'          => $result['status'] ?? $transaction->status,
            'approved_amount' => $result['approved_amount'] ?? $transaction->approved_amount,
            'denial_reason'   => $result['denial_reason'] ?? $transaction->denial_reason,
            'updated'         => $updated,
            'provider_data'   => $result,
        ];
    }

    // ---------------------------------------------------------------
    // Cost Estimation
    // ---------------------------------------------------------------

    /**
     * Get estimated cost for a specialty consultation, factoring in insurance if provided.
     */
    public function getEstimatedCost(string $specialty, ?array $insuranceDetails = null): array
    {
        // TODO: Pull real fee schedules from hospital configuration / fee master
        $baseFees = [
            'general_medicine'  => 200.00,
            'cardiology'        => 500.00,
            'orthopedics'       => 400.00,
            'dermatology'       => 300.00,
            'pediatrics'        => 250.00,
            'gynecology'        => 350.00,
            'neurology'         => 500.00,
            'ophthalmology'     => 300.00,
            'ent'               => 300.00,
            'psychiatry'        => 400.00,
        ];

        $consultationFee = $baseFees[strtolower($specialty)] ?? 300.00;

        if (empty($insuranceDetails)) {
            return [
                'specialty'           => $specialty,
                'consultation_fee'    => $consultationFee,
                'insurance_coverage'  => 0,
                'patient_share'       => $consultationFee,
                'currency'            => 'AED',
                'note'                => 'Self-pay estimate. Insurance details not provided.',
            ];
        }

        $copayPercent = $insuranceDetails['copay_percent'] ?? 20;
        $patientShare = round($consultationFee * ($copayPercent / 100), 2);
        $insuranceCoverage = round($consultationFee - $patientShare, 2);

        return [
            'specialty'           => $specialty,
            'consultation_fee'    => $consultationFee,
            'insurance_coverage'  => $insuranceCoverage,
            'patient_share'       => $patientShare,
            'copay_percent'       => $copayPercent,
            'currency'            => $insuranceDetails['currency'] ?? 'AED',
            'note'                => 'Estimate based on insurance plan. Actual charges may vary.',
        ];
    }

    // ---------------------------------------------------------------
    // Insurance Card OCR
    // ---------------------------------------------------------------

    /**
     * Parse an insurance card image using OCR.
     *
     * TODO: Integrate with actual OCR service (Google Vision, AWS Textract, etc.)
     * Returns structured data extracted from the card image.
     */
    public function parseInsuranceCard(string $imagePath): array
    {
        $this->logInfo('Parsing insurance card image', ['path' => $imagePath]);

        // TODO: integrate a real OCR provider (Google Cloud Vision / AWS Textract
        // / Azure Computer Vision / Tesseract). Until then, do NOT fabricate a
        // policy — return an unparsed result so staff enter the details manually
        // rather than being shown an invented member/policy number.
        return [
            'parsed'        => false,
            'ocr_provider'  => 'none',
            'message'       => 'Automatic card scanning is not configured — please enter the insurance details manually.',
            'insurer_name'  => null,
            'insurer_code'  => null,
            'plan_name'     => null,
            'member_id'     => null,
            'policy_number' => null,
            'member_name'   => null,
            'expiry_date'   => null,
        ];
    }

    // ---------------------------------------------------------------
    // Internal Helpers
    // ---------------------------------------------------------------

    /**
     * Resolve the appropriate insurance provider integration based on insurer code.
     */
    protected function resolveProvider(string $insurerCode): BaseInsuranceProvider
    {
        $provider = InsuranceProvider::tryFrom($insurerCode);

        if ($provider === null) {
            // Default to UAE DHA for unknown providers
            $this->logWarning('Unknown insurer code, defaulting to UAE DHA', [
                'insurer_code' => $insurerCode,
            ]);
            return new UAEDHAProvider();
        }

        return match ($provider->country()) {
            'UAE'   => new UAEDHAProvider(),
            'India' => new IndiaTPAProvider($insurerCode),
            default => new UAEDHAProvider(),
        };
    }

    /**
     * Estimate the total cost for a set of procedure codes.
     *
     * TODO: Pull from hospital fee master / procedure catalog
     */
    protected function estimateProcedureCost(array $procedureCodes): float
    {
        // Stub: assign a default cost per procedure
        $costPerProcedure = 500.00;
        return count($procedureCodes) * $costPerProcedure;
    }
}

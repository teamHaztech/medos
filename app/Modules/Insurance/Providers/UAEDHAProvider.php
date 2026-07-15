<?php

namespace App\Modules\Insurance\Providers;

use Illuminate\Support\Facades\Http;

class UAEDHAProvider extends BaseInsuranceProvider
{
    protected string $endpoint;
    protected string $apiKey;

    public function __construct()
    {
        $this->endpoint = config('medos.insurance.uae_dha_endpoint', 'https://api.dha.gov.ae/eclaims/v1');
        $this->apiKey = config('medos.insurance.uae_dha_key', '');
    }

    public function getProviderCode(): string
    {
        return 'uae_dha';
    }

    /**
     * Verify insurance eligibility via DHA e-Claims.
     */
    public function verifyEligibility(array $insuranceDetails): array
    {
        $this->log('info', 'Verifying eligibility via DHA', [
            'member_id' => $insuranceDetails['member_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('eligibility');
        }

        // TODO: Replace stub with real DHA API call
        // Real implementation would POST to {endpoint}/eligibility
        // with DHA-specific XML/JSON payload format
        $requestPayload = $this->buildEligibilityPayload($insuranceDetails);

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->post("{$this->endpoint}/eligibility", $requestPayload);
            // $data = $response->json();

            // Stub response simulating a successful eligibility check
            $data = [
                'eligible'         => true,
                'plan_name'        => $insuranceDetails['plan_name'] ?? 'Enhanced Plan',
                'copay_percent'    => 20.0,
                'network_type'     => 'in_network',
                'benefits'         => [
                    'consultation'  => ['covered' => true, 'limit' => 500.00, 'currency' => 'AED'],
                    'lab'           => ['covered' => true, 'limit' => 2000.00, 'currency' => 'AED'],
                    'pharmacy'      => ['covered' => true, 'limit' => 1500.00, 'currency' => 'AED'],
                    'inpatient'     => ['covered' => true, 'limit' => 100000.00, 'currency' => 'AED'],
                ],
                'requires_preauth' => false,
                'policy_valid_to'  => now()->addMonths(6)->toDateString(),
                'dha_reference'    => 'DHA-ELG-' . strtoupper(uniqid()),
            ];

            $this->log('info', 'DHA eligibility check successful', ['eligible' => $data['eligible']]);

            return $data;
        } catch (\Throwable $e) {
            $this->log('error', 'DHA eligibility check failed', ['error' => $e->getMessage()]);

            return [
                'eligible'         => false,
                'plan_name'        => null,
                'copay_percent'    => 0,
                'network_type'     => 'unknown',
                'benefits'         => [],
                'requires_preauth' => false,
                'error'            => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit pre-authorization via DHA e-Claims.
     */
    public function submitPreAuth(array $data): array
    {
        $this->log('info', 'Submitting pre-auth via DHA', [
            'encounter_id' => $data['encounter_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('pre-authorization');
        }

        // TODO: Replace stub with real DHA pre-auth API call
        // Real implementation would POST to {endpoint}/preauth
        $requestPayload = $this->buildPreAuthPayload($data);

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->post("{$this->endpoint}/preauth", $requestPayload);

            // Stub response
            return [
                'reference_id'     => 'DHA-PA-' . strtoupper(uniqid()),
                'status'           => 'approved',
                'approved_amount'  => $data['estimated_cost'] ?? 0,
                'approved_items'   => $data['procedure_codes'] ?? [],
                'validity_hours'   => 72,
                'remarks'          => 'Pre-authorization approved',
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'DHA pre-auth submission failed', ['error' => $e->getMessage()]);

            return [
                'reference_id'    => null,
                'status'          => 'error',
                'approved_amount' => 0,
                'error'           => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit a claim via DHA e-Claims.
     */
    public function submitClaim(array $data): array
    {
        $this->log('info', 'Submitting claim via DHA', [
            'encounter_id' => $data['encounter_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('claim');
        }

        // TODO: Replace stub with real DHA claim submission API call
        // Real implementation would POST to {endpoint}/claims
        $requestPayload = $this->buildClaimPayload($data);

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->post("{$this->endpoint}/claims", $requestPayload);

            // Stub response
            return [
                'reference_id'    => 'DHA-CLM-' . strtoupper(uniqid()),
                'status'          => 'submitted',
                'submitted_at'    => now()->toIso8601String(),
                'expected_settlement_days' => 30,
                'remarks'         => 'Claim received and under processing',
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'DHA claim submission failed', ['error' => $e->getMessage()]);

            return [
                'reference_id' => null,
                'status'       => 'error',
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * Check status of a DHA transaction.
     */
    public function checkStatus(string $referenceId): array
    {
        $this->log('info', 'Checking DHA transaction status', ['reference_id' => $referenceId]);

        if (! $this->isConfigured()) {
            return $this->notConnected('status check');
        }

        // TODO: Replace stub with real DHA status check API call
        // Real implementation would GET {endpoint}/status/{referenceId}

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->get("{$this->endpoint}/status/{$referenceId}");

            // Stub response
            return [
                'reference_id'    => $referenceId,
                'status'          => 'approved',
                'approved_amount' => 1500.00,
                'denial_reason'   => null,
                'updated_at'      => now()->toIso8601String(),
                'remarks'         => 'Claim processed and approved',
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'DHA status check failed', ['error' => $e->getMessage()]);

            return [
                'reference_id'  => $referenceId,
                'status'        => 'error',
                'denial_reason' => null,
                'error'         => $e->getMessage(),
            ];
        }
    }

    // ---------------------------------------------------------------
    // Internal Payload Builders
    // ---------------------------------------------------------------

    /**
     * Build DHA-format eligibility request payload.
     *
     * TODO: Map to actual DHA XML/JSON schema
     */
    protected function buildEligibilityPayload(array $insuranceDetails): array
    {
        return [
            'Header' => [
                'SenderID'   => config('medos.insurance.uae_dha_sender_id', 'MEDOS'),
                'ReceiverID' => 'DHA',
                'TransactionDate' => now()->format('Y-m-d\TH:i:s'),
            ],
            'Patient' => [
                'MemberID'     => $insuranceDetails['member_id'] ?? '',
                'EmiratesID'   => $insuranceDetails['emirates_id'] ?? '',
                'PayerID'      => $insuranceDetails['insurer_code'] ?? '',
                'PolicyNumber' => $insuranceDetails['policy_number'] ?? '',
            ],
        ];
    }

    /**
     * Build DHA-format pre-authorization payload.
     *
     * TODO: Map to actual DHA XML/JSON schema
     */
    protected function buildPreAuthPayload(array $data): array
    {
        return [
            'Header' => [
                'SenderID'   => config('medos.insurance.uae_dha_sender_id', 'MEDOS'),
                'ReceiverID' => 'DHA',
                'TransactionDate' => now()->format('Y-m-d\TH:i:s'),
            ],
            'Authorization' => [
                'MemberID'        => $data['member_id'] ?? '',
                'PayerID'         => $data['insurer_code'] ?? '',
                'DiagnosisCodes'  => $data['diagnosis_codes'] ?? [],
                'ProcedureCodes'  => $data['procedure_codes'] ?? [],
                'EstimatedCost'   => $data['estimated_cost'] ?? 0,
                'EncounterType'   => $data['encounter_type'] ?? 'outpatient',
            ],
        ];
    }

    /**
     * Build DHA-format claim payload.
     *
     * TODO: Map to actual DHA XML/JSON schema
     */
    protected function buildClaimPayload(array $data): array
    {
        return [
            'Header' => [
                'SenderID'   => config('medos.insurance.uae_dha_sender_id', 'MEDOS'),
                'ReceiverID' => 'DHA',
                'TransactionDate' => now()->format('Y-m-d\TH:i:s'),
            ],
            'Claim' => [
                'MemberID'          => $data['member_id'] ?? '',
                'PayerID'           => $data['insurer_code'] ?? '',
                'DiagnosisCodes'    => $data['diagnosis_codes'] ?? [],
                'ProcedureCodes'    => $data['procedure_codes'] ?? [],
                'TotalAmount'       => $data['total_amount'] ?? 0,
                'EncounterStartDate'=> $data['encounter_start'] ?? now()->toDateString(),
                'EncounterEndDate'  => $data['encounter_end'] ?? now()->toDateString(),
                'LineItems'         => $data['line_items'] ?? [],
                'PreAuthRef'        => $data['pre_auth_reference'] ?? null,
            ],
        ];
    }

    /**
     * Build standard DHA API headers.
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-DHA-Facility-ID' => config('medos.insurance.uae_dha_facility_id', ''),
        ];
    }
}

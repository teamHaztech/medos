<?php

namespace App\Modules\Insurance\Providers;

use Illuminate\Support\Facades\Http;

class IndiaTPAProvider extends BaseInsuranceProvider
{
    protected string $tpaCode;
    protected string $endpoint;
    protected string $apiKey;

    /**
     * @param  string  $tpaCode  The TPA identifier (e.g., 'mediassist', 'paramount', 'pmjay')
     */
    public function __construct(string $tpaCode = 'mediassist')
    {
        $this->tpaCode = $tpaCode;
        $this->endpoint = config("medos.insurance.india_tpa.{$tpaCode}.endpoint", '');
        $this->apiKey = config("medos.insurance.india_tpa.{$tpaCode}.api_key", '');
    }

    public function getProviderCode(): string
    {
        return "india_tpa_{$this->tpaCode}";
    }

    /**
     * Verify insurance eligibility with the TPA.
     */
    public function verifyEligibility(array $insuranceDetails): array
    {
        $this->log('info', 'Verifying eligibility with Indian TPA', [
            'tpa'       => $this->tpaCode,
            'member_id' => $insuranceDetails['member_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('eligibility');
        }

        // TODO: Replace stubs with real TPA API calls
        // Each TPA (MediAssist, Paramount, Vidal Health) has its own API format.
        // PMJAY uses the National Health Authority (NHA) API.

        if ($this->tpaCode === 'pmjay') {
            return $this->verifyPMJAYEligibility($insuranceDetails);
        }

        try {
            // TODO: Uncomment for real TPA integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->post("{$this->endpoint}/eligibility", [
            //         'member_id'     => $insuranceDetails['member_id'] ?? '',
            //         'policy_number' => $insuranceDetails['policy_number'] ?? '',
            //         'dob'           => $insuranceDetails['dob'] ?? '',
            //     ]);

            // Stub response for generic TPA
            return [
                'eligible'         => true,
                'plan_name'        => $insuranceDetails['plan_name'] ?? 'Corporate Health Plan',
                'copay_percent'    => 10.0,
                'network_type'     => 'in_network',
                'benefits'         => [
                    'consultation'  => ['covered' => true, 'limit' => 1000.00, 'currency' => 'INR'],
                    'lab'           => ['covered' => true, 'limit' => 5000.00, 'currency' => 'INR'],
                    'pharmacy'      => ['covered' => true, 'limit' => 3000.00, 'currency' => 'INR'],
                    'inpatient'     => ['covered' => true, 'limit' => 500000.00, 'currency' => 'INR'],
                ],
                'requires_preauth' => true,
                'sum_insured'      => 500000.00,
                'sum_remaining'    => 450000.00,
                'tpa_reference'    => strtoupper($this->tpaCode) . '-ELG-' . strtoupper(uniqid()),
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'TPA eligibility check failed', ['error' => $e->getMessage()]);

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
     * Submit pre-authorization (cashless authorization) to TPA.
     */
    public function submitPreAuth(array $data): array
    {
        $this->log('info', 'Submitting cashless pre-auth to TPA', [
            'tpa'          => $this->tpaCode,
            'encounter_id' => $data['encounter_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('pre-authorization');
        }

        // TODO: Replace stub with real TPA cashless authorization API call
        // Cashless flow: Hospital submits pre-auth -> TPA reviews -> approves/denies
        // MediAssist uses REST API, Paramount uses SOAP, etc.

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->post("{$this->endpoint}/preauth", $this->buildPreAuthPayload($data));

            // Stub response
            return [
                'reference_id'     => strtoupper($this->tpaCode) . '-PA-' . strtoupper(uniqid()),
                'status'           => 'pending_review',
                'approved_amount'  => 0, // TPA will review and set approved amount
                'approved_items'   => [],
                'estimated_tat'    => '2-4 hours',
                'remarks'          => 'Cashless request submitted, awaiting TPA desk review',
                'cashless'         => true,
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'TPA pre-auth submission failed', ['error' => $e->getMessage()]);

            return [
                'reference_id'    => null,
                'status'          => 'error',
                'approved_amount' => 0,
                'error'           => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit a claim to the TPA.
     */
    public function submitClaim(array $data): array
    {
        $this->log('info', 'Submitting claim to TPA', [
            'tpa'          => $this->tpaCode,
            'encounter_id' => $data['encounter_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('claim');
        }

        // TODO: Replace stub with real TPA claim API call

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->post("{$this->endpoint}/claims", $this->buildClaimPayload($data));

            // Stub response
            return [
                'reference_id'              => strtoupper($this->tpaCode) . '-CLM-' . strtoupper(uniqid()),
                'status'                    => 'submitted',
                'submitted_at'              => now()->toIso8601String(),
                'expected_settlement_days'  => 15,
                'remarks'                   => 'Claim received by TPA for processing',
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'TPA claim submission failed', ['error' => $e->getMessage()]);

            return [
                'reference_id' => null,
                'status'       => 'error',
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * Check status of a TPA transaction.
     */
    public function checkStatus(string $referenceId): array
    {
        $this->log('info', 'Checking TPA transaction status', [
            'tpa'          => $this->tpaCode,
            'reference_id' => $referenceId,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('status check');
        }

        // TODO: Replace stub with real TPA status API call

        try {
            // TODO: Uncomment for real integration
            // $response = Http::withHeaders($this->getHeaders())
            //     ->timeout(30)
            //     ->get("{$this->endpoint}/status/{$referenceId}");

            // Stub response
            return [
                'reference_id'    => $referenceId,
                'status'          => 'approved',
                'approved_amount' => 25000.00,
                'denial_reason'   => null,
                'updated_at'      => now()->toIso8601String(),
                'remarks'         => 'Claim approved by TPA',
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'TPA status check failed', ['error' => $e->getMessage()]);

            return [
                'reference_id'  => $referenceId,
                'status'        => 'error',
                'denial_reason' => null,
                'error'         => $e->getMessage(),
            ];
        }
    }

    // ---------------------------------------------------------------
    // PMJAY (Ayushman Bharat) Specific
    // ---------------------------------------------------------------

    /**
     * Verify eligibility via PMJAY / NHA API.
     *
     * TODO: Implement real NHA API integration
     * NHA provides ABHA (Ayushman Bharat Health Account) verification
     */
    protected function verifyPMJAYEligibility(array $insuranceDetails): array
    {
        $this->log('info', 'Verifying PMJAY eligibility via NHA API', [
            'abha_id' => $insuranceDetails['abha_id'] ?? $insuranceDetails['member_id'] ?? null,
        ]);

        if (! $this->isConfigured()) {
            return $this->notConnected('PMJAY eligibility');
        }

        // TODO: Replace stub with real NHA/PMJAY API call
        // NHA API endpoint: https://pmjay.gov.in/api/v1/verify
        // Requires facility registration with NHA

        // Stub response
        return [
            'eligible'         => true,
            'plan_name'        => 'PMJAY - Ayushman Bharat',
            'copay_percent'    => 0.0, // PMJAY is fully government-funded
            'network_type'     => 'empanelled',
            'benefits'         => [
                'consultation'  => ['covered' => true, 'limit' => null, 'currency' => 'INR'],
                'lab'           => ['covered' => true, 'limit' => null, 'currency' => 'INR'],
                'pharmacy'      => ['covered' => true, 'limit' => null, 'currency' => 'INR'],
                'inpatient'     => ['covered' => true, 'limit' => 500000.00, 'currency' => 'INR'],
            ],
            'requires_preauth' => true,
            'sum_insured'      => 500000.00,
            'family_id'        => $insuranceDetails['family_id'] ?? null,
            'abha_verified'    => true,
            'nha_reference'    => 'PMJAY-ELG-' . strtoupper(uniqid()),
        ];
    }

    /**
     * Build standard TPA API headers.
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-TPA-Code'    => $this->tpaCode,
        ];
    }
}

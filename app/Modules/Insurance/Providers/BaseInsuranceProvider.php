<?php

namespace App\Modules\Insurance\Providers;

use Illuminate\Support\Facades\Log;

abstract class BaseInsuranceProvider
{
    /**
     * The provider code used in configuration and storage.
     */
    abstract public function getProviderCode(): string;

    /**
     * Verify insurance eligibility for the given insurance details.
     *
     * @param  array  $insuranceDetails  ['policy_number', 'member_id', 'insurer_code', ...]
     * @return array  ['eligible' => bool, 'plan_name' => string, 'copay_percent' => float, ...]
     */
    abstract public function verifyEligibility(array $insuranceDetails): array;

    /**
     * Submit a pre-authorization request.
     *
     * @param  array  $data  ['patient', 'encounter', 'diagnosis_codes', 'procedure_codes', 'estimated_cost', ...]
     * @return array  ['reference_id' => string, 'status' => string, 'approved_amount' => float, ...]
     */
    abstract public function submitPreAuth(array $data): array;

    /**
     * Submit a claim for reimbursement/payment.
     *
     * @param  array  $data  ['patient', 'encounter', 'diagnosis_codes', 'procedure_codes', 'bill', ...]
     * @return array  ['reference_id' => string, 'status' => string, ...]
     */
    abstract public function submitClaim(array $data): array;

    /**
     * Check the status of a previously submitted transaction.
     *
     * @param  string  $referenceId  The provider-assigned reference ID
     * @return array  ['status' => string, 'approved_amount' => float, 'denial_reason' => ?string, ...]
     */
    abstract public function checkStatus(string $referenceId): array;

    /**
     * Log a provider-level message.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $context['provider'] = $this->getProviderCode();
        Log::log($level, "[MedOS][Insurance][{$this->getProviderCode()}] {$message}", $context);
    }
}

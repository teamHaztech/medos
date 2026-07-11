<?php

namespace App\Modules\Billing\Integrations;

use Illuminate\Support\Facades\Log;

/**
 * Contract every external-billing connector implements. Mirrors the Insurance
 * BaseInsuranceProvider pattern: adding a new billing software = one subclass
 * registered in BillingConnectorRegistry — no changes elsewhere.
 */
abstract class BaseBillingConnector
{
    /** Stable machine code stored in hospital config (e.g. 'generic_webhook'). */
    abstract public function code(): string;

    /** Human label for the settings dropdown. */
    abstract public function label(): string;

    /**
     * Fields the settings UI should collect for this connector.
     * Each: ['key','label','type' => url|text|secret,'required'=>bool,'help'=>string].
     * Fields with type 'secret' are stored encrypted and never returned to the view.
     */
    abstract public function configFields(): array;

    /**
     * Push a normalized bill payload to the external system.
     *
     * @param  array  $payload  canonical bill payload (see BillPayload)
     * @param  array  $config   decrypted config: endpoint + secrets
     * @return array  ['ok'=>bool, 'http_status'=>?int, 'response'=>string]
     */
    abstract public function push(array $payload, array $config): array;

    /**
     * Verify connectivity/credentials (used by the "Send test" button).
     *
     * @return array  ['ok'=>bool, 'message'=>string]
     */
    abstract public function test(array $config): array;

    /** Which secret config keys must be stored encrypted. */
    public function secretKeys(): array
    {
        return array_values(array_map(
            fn ($f) => $f['key'],
            array_filter($this->configFields(), fn ($f) => ($f['type'] ?? '') === 'secret')
        ));
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        $context['connector'] = $this->code();
        Log::log($level, "[MedOS][BillingIntegration][{$this->code()}] {$message}", $context);
    }
}

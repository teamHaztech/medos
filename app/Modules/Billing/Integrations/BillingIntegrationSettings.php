<?php

namespace App\Modules\Billing\Integrations;

use App\Modules\Core\Models\Hospital;
use Illuminate\Support\Facades\Log;

/**
 * Reads/writes the per-hospital billing-integration config, which lives under
 * Hospital.config['billing_integration'] (secrets encrypted at the value level,
 * mirroring the AI/WhatsApp settings).
 */
class BillingIntegrationSettings
{
    /** Raw config section (secrets still encrypted), or null if not configured. */
    public static function for(string $hospitalId): ?array
    {
        $hospital = Hospital::find($hospitalId);
        if (! $hospital) {
            return null;
        }
        $cfg = is_array($hospital->config) ? $hospital->config : json_decode($hospital->config ?? '{}', true);

        return $cfg['billing_integration'] ?? null;
    }

    public static function isActive(string $hospitalId): bool
    {
        $bi = self::for($hospitalId);

        return $bi && ($bi['enabled'] ?? false) && ! empty($bi['connector']);
    }

    /** Endpoint + decrypted secrets for the connector to use at push time. */
    public static function credentials(array $bi): array
    {
        $out = ['endpoint' => $bi['endpoint'] ?? null];

        foreach (['secret', 'signing_secret'] as $key) {
            if (empty($bi[$key])) {
                continue;
            }
            try {
                $out[$key] = decrypt($bi[$key]);
            } catch (\Throwable $e) {
                Log::warning('[MedOS][BillingIntegration] could not decrypt ' . $key, ['error' => $e->getMessage()]);
                $out[$key] = null;
            }
        }

        return $out;
    }
}

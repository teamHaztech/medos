<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Hospital;

class RegionService
{
    private static ?array $regionConfig = null;
    private static ?string $countryCode = null;

    /**
     * Get region config for current hospital or given country code.
     */
    public static function get(?string $countryCode = null): array
    {
        $code = $countryCode ?? static::getCountryCode();
        return config("regions.{$code}", config('regions.IN'));
    }

    /**
     * Get current country code from hospital context.
     */
    public static function getCountryCode(): string
    {
        if (static::$countryCode) return static::$countryCode;

        $hospitalId = config('medos.current_hospital_id');
        if ($hospitalId) {
            $hospital = Hospital::find($hospitalId);
            if ($hospital) {
                static::$countryCode = $hospital->country ?? 'IN';
                return static::$countryCode;
            }
        }

        // Try from auth
        $user = auth()->user();
        if ($user?->hospital) {
            static::$countryCode = $user->hospital->country ?? 'IN';
            return static::$countryCode;
        }

        return 'IN'; // default
    }

    /**
     * Set country code explicitly (for testing, CLI, etc.)
     */
    public static function setCountryCode(string $code): void
    {
        static::$countryCode = $code;
        static::$regionConfig = null;
    }

    /**
     * Reset cached values.
     */
    public static function reset(): void
    {
        static::$countryCode = null;
        static::$regionConfig = null;
    }

    // ---------------------------------------------------------------
    // Convenience methods
    // ---------------------------------------------------------------

    public static function isIndia(): bool { return static::getCountryCode() === 'IN'; }
    public static function isUAE(): bool { return static::getCountryCode() === 'AE'; }

    public static function currency(): string { return static::get()['currency']; }
    public static function currencyCode(): string { return static::get()['currency_code']; }
    public static function phonePrefix(): string { return static::get()['phone_prefix']; }
    public static function phoneDigits(): int { return static::get()['phone_digits']; }
    public static function timezone(): string { return static::get()['timezone']; }
    public static function emergencyNumber(): string { return static::get()['emergency_number']; }

    public static function languages(): array { return static::get()['languages']; }
    public static function defaultLanguage(): string { return static::get()['default_language']; }
    public static function voiceLanguages(): array { return static::get()['voice_languages']; }

    public static function healthIdSystem(): array { return static::get()['health_id']; }
    public static function healthIdLabel(): string { return static::get()['health_id']['field_label']; }
    public static function healthIdEnabled(): bool { return static::get()['health_id']['enabled']; }

    public static function insuranceProviders(): array { return static::get()['insurance']['providers']; }
    public static function insuranceSystem(): string { return static::get()['insurance']['system']; }

    public static function paymentMethods(): array { return static::get()['payment_methods']; }
    public static function taxName(): string { return static::get()['tax_name']; }
    public static function taxRate(): int { return static::get()['tax_rate']; }

    public static function regulations(): array { return static::get()['regulations']; }

    /**
     * Format currency amount.
     */
    public static function formatMoney(float $amount): string
    {
        return static::currency() . number_format($amount, 2);
    }

    /**
     * Normalize phone number for the region.
     */
    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $digits = static::phoneDigits();
        $prefix = static::phonePrefix();

        if (strlen($cleaned) === $digits) {
            return $prefix . $cleaned;
        }
        if (str_starts_with($cleaned, ltrim($prefix, '+'))) {
            return '+' . $cleaned;
        }
        return $prefix . substr($cleaned, -$digits);
    }

    /**
     * Validate health ID format for the region.
     */
    public static function validateHealthId(string $id): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $id);
        return strlen($cleaned) === static::get()['health_id']['digits'];
    }

    /**
     * Format health ID for display.
     */
    public static function formatHealthId(string $id): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $id);

        if (static::isIndia() && strlen($cleaned) === 14) {
            return substr($cleaned, 0, 2) . '-' . substr($cleaned, 2, 4) . '-' . substr($cleaned, 6, 4) . '-' . substr($cleaned, 10, 4);
        }

        if (static::isUAE() && strlen($cleaned) === 15) {
            return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 4) . '-' . substr($cleaned, 7, 7) . '-' . substr($cleaned, 14, 1);
        }

        return $id;
    }
}

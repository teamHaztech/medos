<?php

namespace App\Modules\Insurance\Enums;

enum InsuranceProvider: string
{
    // UAE Providers
    case Daman          = 'daman';
    case NAS            = 'nas';
    case OmanInsurance  = 'oman_insurance';
    case AXA            = 'axa';
    case Cigna          = 'cigna';
    case MetLife        = 'metlife';
    case Allianz        = 'allianz';

    // India Providers
    case PMJAY          = 'pmjay';
    case MediAssist     = 'mediassist';
    case Paramount      = 'paramount';
    case VidalHealth    = 'vidal_health';
    case StarHealth     = 'star_health';
    case ICICI_Lombard  = 'icici_lombard';

    /**
     * Get a human-readable label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daman          => 'Daman Health Insurance',
            self::NAS            => 'NAS Insurance',
            self::OmanInsurance  => 'Oman Insurance Company',
            self::AXA            => 'AXA Insurance (Gulf)',
            self::Cigna          => 'Cigna',
            self::MetLife        => 'MetLife',
            self::Allianz        => 'Allianz Care',
            self::PMJAY          => 'Pradhan Mantri Jan Arogya Yojana (Ayushman Bharat)',
            self::MediAssist     => 'Medi Assist TPA',
            self::Paramount      => 'Paramount Health Services TPA',
            self::VidalHealth    => 'Vidal Health TPA',
            self::StarHealth     => 'Star Health & Allied Insurance',
            self::ICICI_Lombard  => 'ICICI Lombard General Insurance',
        };
    }

    /**
     * Get the country/region for the provider.
     */
    public function country(): string
    {
        return match ($this) {
            self::Daman,
            self::NAS,
            self::OmanInsurance,
            self::AXA,
            self::Cigna,
            self::MetLife,
            self::Allianz        => 'UAE',

            self::PMJAY,
            self::MediAssist,
            self::Paramount,
            self::VidalHealth,
            self::StarHealth,
            self::ICICI_Lombard  => 'India',
        };
    }

    /**
     * Whether the provider supports direct API integration.
     */
    public function apiSupported(): bool
    {
        return match ($this) {
            self::Daman          => true,
            self::NAS            => true,
            self::OmanInsurance  => false,
            self::AXA            => true,
            self::Cigna          => true,
            self::MetLife        => false,
            self::Allianz        => false,
            self::PMJAY          => true,
            self::MediAssist     => true,
            self::Paramount      => true,
            self::VidalHealth    => true,
            self::StarHealth     => false,
            self::ICICI_Lombard  => false,
        };
    }

    /**
     * Get all providers for a given country.
     *
     * @return self[]
     */
    public static function forCountry(string $country): array
    {
        return array_filter(self::cases(), fn (self $p) => $p->country() === $country);
    }

    /**
     * Get all providers that support API integration.
     *
     * @return self[]
     */
    public static function apiEnabled(): array
    {
        return array_filter(self::cases(), fn (self $p) => $p->apiSupported());
    }
}

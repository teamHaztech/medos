<?php

namespace App\Modules\Billing\Integrations;

/**
 * The one place connectors are registered. To add support for another billing
 * software, write a BaseBillingConnector subclass and add one line here.
 */
class BillingConnectorRegistry
{
    /** @var array<string, class-string<BaseBillingConnector>> */
    public const CONNECTORS = [
        'generic_webhook' => GenericWebhookConnector::class,
    ];

    public static function make(?string $code): ?BaseBillingConnector
    {
        $class = self::CONNECTORS[$code] ?? null;

        return $class ? new $class() : null;
    }

    /** @return BaseBillingConnector[] — for the settings dropdown. */
    public static function all(): array
    {
        return array_map(fn ($class) => new $class(), array_values(self::CONNECTORS));
    }
}

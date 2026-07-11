<?php

namespace App\Modules\Billing\Integrations;

use Illuminate\Support\Facades\Http;

/**
 * Universal connector: POSTs each bill as JSON to a hospital-configured URL.
 * Works with virtually any external billing/accounting system, iPaaS (Zapier,
 * n8n, Make), or custom HMIS that can receive a webhook. Optionally bearer-auths
 * and HMAC-signs the body so the receiver can verify authenticity.
 */
class GenericWebhookConnector extends BaseBillingConnector
{
    public function code(): string
    {
        return 'generic_webhook';
    }

    public function label(): string
    {
        return 'Generic Webhook / REST API';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'endpoint', 'label' => 'Endpoint URL', 'type' => 'url', 'required' => true,
                'help' => 'MedOS sends an HTTP POST with the bill as JSON to this URL.'],
            ['key' => 'secret', 'label' => 'API key / Bearer token', 'type' => 'secret', 'required' => false,
                'help' => 'If set, sent as "Authorization: Bearer …".'],
            ['key' => 'signing_secret', 'label' => 'Signing secret (HMAC)', 'type' => 'secret', 'required' => false,
                'help' => 'If set, the body is signed — header "X-MedOS-Signature: sha256=…" — so you can verify it.'],
        ];
    }

    public function push(array $payload, array $config): array
    {
        $endpoint = $config['endpoint'] ?? '';
        if (! $endpoint) {
            return ['ok' => false, 'http_status' => null, 'response' => 'No endpoint URL configured.'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $request = Http::timeout(15)->withBody($body, 'application/json');
        if (! empty($config['secret'])) {
            $request = $request->withToken($config['secret']);
        }
        if (! empty($config['signing_secret'])) {
            $request = $request->withHeaders([
                'X-MedOS-Signature' => 'sha256=' . hash_hmac('sha256', $body, $config['signing_secret']),
            ]);
        }

        try {
            // Single attempt — the queued job retries transient failures.
            $res = $request->post($endpoint);

            return [
                'ok'          => $res->successful(),
                'http_status' => $res->status(),
                'response'    => mb_substr($res->body(), 0, 500),
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'push failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'http_status' => null, 'response' => $e->getMessage()];
        }
    }

    public function test(array $config): array
    {
        $result = $this->push([
            'event'       => 'test.ping',
            'source'      => 'medos',
            'message'     => 'MedOS billing-integration connectivity test.',
            'occurred_at' => now()->toIso8601String(),
        ], $config);

        if ($result['ok']) {
            return ['ok' => true, 'message' => 'Endpoint responded (HTTP ' . $result['http_status'] . ').'];
        }

        $detail = $result['response'] ?: 'no response';
        $http = $result['http_status'] ? ' (HTTP ' . $result['http_status'] . ')' : '';

        return ['ok' => false, 'message' => 'Test failed: ' . $detail . $http];
    }
}

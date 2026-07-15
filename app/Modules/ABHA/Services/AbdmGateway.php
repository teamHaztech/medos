<?php

namespace App\Modules\ABHA\Services;

use App\Modules\Core\Models\Hospital;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transport layer for ABDM gateway calls: reads a hospital's ABDM credentials
 * from Hospital.config['abdm'], obtains + caches a gateway session (access) token
 * via the client-credentials flow, and exposes authenticated request helpers.
 *
 * The HTTP/token-caching machinery here is standard and complete. The ABDM-
 * specific bits are isolated as the SESSION_PATH constant + the session payload
 * keys below — VERIFY these against the ABDM sandbox's API/Postman collection
 * once gateway access is granted (endpoint versions change).
 */
class AbdmGateway
{
    // VERIFY against ABDM sandbox docs on access — the M1 gateway session endpoint.
    private const SESSION_PATH = '/gateway/v0.5/sessions';

    public function __construct(private string $hospitalId) {}

    public static function for(string $hospitalId): self
    {
        return new self($hospitalId);
    }

    /** ABDM config for the hospital (client_secret is stored encrypted). */
    public function config(): array
    {
        $hospital = Hospital::find($this->hospitalId);
        $config = is_array($hospital?->config) ? $hospital->config : json_decode($hospital?->config ?? '{}', true);

        return $config['abdm'] ?? [];
    }

    public function isReady(): bool
    {
        $c = $this->config();

        return ! empty($c['client_id']) && ! empty($c['client_secret']) && ! empty($c['base_url']);
    }

    public function baseUrl(): string
    {
        return rtrim($this->config()['base_url'] ?? '', '/');
    }

    private function clientSecret(): string
    {
        $secret = $this->config()['client_secret'] ?? '';
        if ($secret === '') {
            return '';
        }
        try {
            return decrypt($secret);
        } catch (\Throwable $e) {
            return $secret; // tolerate a plaintext value in dev
        }
    }

    /**
     * Obtain (and cache) a gateway session access token via client credentials.
     * Cached per hospital until shortly before it expires.
     */
    public function accessToken(): ?string
    {
        if (! $this->isReady()) {
            return null;
        }

        $cacheKey = 'abdm.token.' . $this->hospitalId;

        return Cache::remember($cacheKey, now()->addMinutes(9), function () {
            $res = Http::acceptJson()->asJson()->post($this->baseUrl() . self::SESSION_PATH, [
                'clientId'     => $this->config()['client_id'],
                'clientSecret' => $this->clientSecret(),
            ]);

            if (! $res->successful()) {
                Log::warning('ABDM session token request failed', ['status' => $res->status()]);

                return null;
            }

            // ABDM returns { accessToken, expiresIn, tokenType, ... }
            return $res->json('accessToken');
        });
    }

    /**
     * Authenticated JSON request to an ABDM endpoint (path relative to base URL).
     *
     * @return array{ok: bool, status: int, body: array}
     */
    public function request(string $method, string $path, array $body = [], array $headers = []): array
    {
        $token = $this->accessToken();
        if (! $token) {
            return ['ok' => false, 'status' => 0, 'body' => ['error' => 'ABDM not configured or session token unavailable']];
        }

        $res = Http::withToken($token)
            ->acceptJson()
            ->withHeaders($headers)
            ->send(strtoupper($method), $this->baseUrl() . '/' . ltrim($path, '/'), [
                'json' => $body,
            ]);

        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => (array) $res->json()];
    }

    public function post(string $path, array $body = [], array $headers = []): array
    {
        return $this->request('POST', $path, $body, $headers);
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, [], $headers);
    }
}

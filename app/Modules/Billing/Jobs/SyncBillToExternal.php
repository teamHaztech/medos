<?php

namespace App\Modules\Billing\Jobs;

use App\Modules\Billing\Integrations\BillingConnectorRegistry;
use App\Modules\Billing\Integrations\BillingIntegrationSettings;
use App\Modules\Billing\Integrations\BillPayload;
use App\Modules\Billing\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pushes one bill event to the hospital's configured external billing software.
 * Queued + retried, mirroring SendWhatsAppMessage. Every attempt is recorded in
 * billing_integration_logs so admins can see sync status.
 */
class SyncBillToExternal implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $billId,
        public readonly string $hospitalId,
        public readonly string $event,
    ) {
        $this->onQueue('billing');
    }

    public function handle(): void
    {
        $bi = BillingIntegrationSettings::for($this->hospitalId);
        if (! $bi || ! ($bi['enabled'] ?? false)) {
            return; // disabled since dispatch — nothing to do
        }

        $connectorCode = $bi['connector'] ?? '';
        $connector = BillingConnectorRegistry::make($connectorCode);
        if (! $connector) {
            $this->logRow($connectorCode, 'skipped', null, 'Unknown connector: ' . $connectorCode);

            return;
        }

        $bill = Bill::withoutGlobalScopes()->find($this->billId);
        if (! $bill) {
            $this->logRow($connectorCode, 'skipped', null, 'Bill not found');

            return;
        }

        $credentials = BillingIntegrationSettings::credentials($bi);
        $payload = BillPayload::fromBill($bill, $this->event);
        $result = $connector->push($payload, $credentials);

        $this->logRow($connectorCode, $result['ok'] ? 'success' : 'failed', $result['http_status'] ?? null, $result['response'] ?? '');

        if (! $result['ok']) {
            // Throw so the queue retries; the log row above records this attempt.
            throw new \RuntimeException('External billing push failed (HTTP ' . ($result['http_status'] ?? 'n/a') . ')');
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::critical('[MedOS][BillingIntegration] push permanently failed', [
            'bill'  => $this->billId,
            'event' => $this->event,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function logRow(string $connector, string $status, ?int $httpStatus, string $response): void
    {
        try {
            DB::table('billing_integration_logs')->insert([
                'id'          => (string) Str::uuid(),
                'hospital_id' => $this->hospitalId,
                'bill_id'     => $this->billId,
                'connector'   => $connector,
                'event'       => $this->event,
                'status'      => $status,
                'http_status' => $httpStatus,
                'response'    => mb_substr($response, 0, 1000),
                'attempts'    => $this->attempts(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MedOS][BillingIntegration] failed to write sync log', ['error' => $e->getMessage()]);
        }
    }
}

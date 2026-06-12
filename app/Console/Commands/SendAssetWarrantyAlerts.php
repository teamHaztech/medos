<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Asset\Models\AssetWarranty;
use App\Modules\Core\Models\NotificationLog;
use App\Notifications\AssetWarrantyExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendAssetWarrantyAlerts extends Command
{
    protected $signature = 'medos:asset-warranty-alerts';

    protected $description = 'Alert hospitals about asset warranties/AMC/CMC expiring within their reminder window';

    public function handle(): int
    {
        try {
            // Runs globally (no hospital context in CLI -> scope is inert, spans all hospitals).
            // Pull active, not-yet-expired warranties within the widest possible window,
            // then keep those inside each row's own reminder window.
            $candidates = AssetWarranty::where('is_active', true)
                ->whereDate('end_date', '>=', now()->toDateString())
                ->whereDate('end_date', '<=', now()->addDays(365)->toDateString())
                ->with('asset')
                ->get()
                ->filter(fn (AssetWarranty $w) => $w->isExpiringWithin($w->reminder_days_before_expiry ?: 30));

            $logged = 0;
            $byHospital = [];

            foreach ($candidates as $w) {
                $stamp = 'asset_warranty:' . $w->id . ':' . now()->format('Ymd');

                // De-dupe: one alert per warranty per day.
                $already = NotificationLog::withoutHospitalScope()
                    ->where('external_id', $stamp)
                    ->exists();
                if ($already) {
                    continue;
                }

                NotificationLog::create([
                    'id'          => (string) Str::uuid(),
                    'hospital_id' => $w->hospital_id,
                    'channel'     => 'web',
                    'type'        => 'asset_warranty_expiring',
                    'status'      => 'logged',
                    'external_id' => $stamp,
                    'content'     => [
                        'asset_id'      => $w->asset_id,
                        'asset_name'    => $w->asset?->asset_name,
                        'warranty_type' => $w->warranty_type,
                        'end_date'      => optional($w->end_date)->toDateString(),
                        'days_to_expiry' => $w->daysToExpiry(),
                    ],
                    'sent_at'     => now(),
                ]);

                $byHospital[$w->hospital_id][] = $w;
                $logged++;
            }

            // Optionally email hospital admins — only when a real mail transport is set up.
            $mailer = config('mail.default');
            if ($mailer && ! in_array($mailer, ['log', 'array', null], true)) {
                foreach ($byHospital as $hospitalId => $warranties) {
                    $admins = User::where('hospital_id', $hospitalId)
                        ->whereIn('role', ['hospital_admin', 'super_admin'])
                        ->whereNotNull('email')
                        ->get();
                    if ($admins->isNotEmpty()) {
                        try {
                            Notification::send($admins, new AssetWarrantyExpiring($warranties));
                        } catch (\Throwable $e) {
                            Log::warning('[MedOS] Asset warranty email failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            $this->info("Logged {$logged} warranty expiry alert(s).");
            Log::info('[MedOS] Asset warranty alerts processed', ['count' => $logged]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");
            Log::error('[MedOS] Asset warranty alerts command failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}

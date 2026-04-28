<?php

namespace App\Modules\Core\Listeners;

use App\Modules\Patient\Models\Patient;
use App\Modules\Queue\Events\QueueUpdated;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class QueueUpdateNotifier implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'whatsapp';

    public int $tries = 2;

    public function __construct(
        private WhatsAppService $whatsAppService,
    ) {}

    public function handle(QueueUpdated $event): void
    {
        if (! config('medos.notifications.queue_update_enabled', true)) {
            return;
        }

        try {
            foreach ($event->changes as $change) {
                $patientId = $change['patient_id'] ?? null;
                $oldPosition = $change['old_position'] ?? 0;
                $newPosition = $change['new_position'] ?? 0;

                if (! $patientId) {
                    continue;
                }

                // Only notify if position changed by more than 1
                $positionDelta = abs($oldPosition - $newPosition);

                if ($positionDelta <= 1) {
                    continue;
                }

                $patient = Patient::find($patientId);

                if (! $patient || ! $patient->phone) {
                    continue;
                }

                $estimatedWait = $change['estimated_wait_minutes'] ?? null;
                $waitText = $estimatedWait
                    ? "Estimated wait: ~{$estimatedWait} minutes."
                    : '';

                $message = "Queue Update: Your position is now #{$newPosition}. {$waitText}";

                try {
                    $this->whatsAppService->sendTextMessage($patient->phone, trim($message));

                    Log::info('[MedOS] Queue update notification sent', [
                        'patient_id'   => $patientId,
                        'new_position' => $newPosition,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[MedOS] Failed to send queue update notification', [
                        'patient_id' => $patientId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[MedOS] Queue update notifier failed', [
                'doctor_id' => $event->doctorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}

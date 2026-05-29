<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Models\NotificationLog;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * Calculate the number of seconds to wait before retrying (exponential backoff).
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $phone,
        public readonly string $messageType,
        public readonly array $content,
        public readonly ?string $hospitalId = null,
        public readonly ?string $patientId = null,
        public readonly ?string $notificationType = null,
    ) {
        $this->onQueue('whatsapp');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * Rate limit: Meta allows ~80 msgs/sec per phone number.
     * We use a conservative limit to stay safely under.
     */
    public function middleware(): array
    {
        return [
            new RateLimited('whatsapp-send'),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        $result = null;

        try {
            $result = match ($this->messageType) {
                'text' => $whatsAppService->sendTextMessage(
                    $this->phone,
                    $this->content['text'] ?? ''
                ),

                'template' => $whatsAppService->sendTemplateMessage(
                    $this->phone,
                    $this->content['template_name'] ?? '',
                    $this->content['language_code'] ?? 'en',
                    $this->content['components'] ?? []
                ),

                'interactive_buttons' => $whatsAppService->sendInteractiveButtons(
                    $this->phone,
                    $this->content['body'] ?? '',
                    $this->content['buttons'] ?? []
                ),

                'interactive_list' => $whatsAppService->sendInteractiveList(
                    $this->phone,
                    $this->content['body'] ?? '',
                    $this->content['button_text'] ?? 'Options',
                    $this->content['sections'] ?? []
                ),

                'document' => $whatsAppService->sendDocument(
                    $this->phone,
                    $this->content['document_url'] ?? '',
                    $this->content['filename'] ?? 'document',
                    $this->content['caption'] ?? null
                ),

                'image' => $whatsAppService->sendImage(
                    $this->phone,
                    $this->content['image_url'] ?? '',
                    $this->content['caption'] ?? null
                ),

                'location_request' => $whatsAppService->sendLocationRequest(
                    $this->phone,
                    $this->content['body'] ?? 'Please share your location'
                ),

                default => throw new \InvalidArgumentException(
                    "Unknown WhatsApp message type: {$this->messageType}"
                ),
            };

            // Log the sent message
            $this->logNotification($result);

            Log::info('[MedOS][WhatsApp] Message sent', [
                'phone'        => $this->phone,
                'type'         => $this->messageType,
                'message_id'   => $result['messages'][0]['id'] ?? null,
                'hospital_id'  => $this->hospitalId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS][WhatsApp] Failed to send message', [
                'phone'   => $this->phone,
                'type'    => $this->messageType,
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
            ]);

            // Log the failure
            $this->logNotification($result, $e->getMessage());

            throw $e; // Let the queue retry
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::critical('[MedOS][WhatsApp] Message send permanently failed', [
            'phone'   => $this->phone,
            'type'    => $this->messageType,
            'error'   => $exception?->getMessage(),
        ]);
    }

    /**
     * Log the notification to the notifications_log table.
     */
    private function logNotification(?array $result, ?string $error = null): void
    {
        try {
            $externalId = $result['messages'][0]['id'] ?? null;
            $hasError = $error !== null || ($result['_error'] ?? false);

            NotificationLog::create([
                'hospital_id' => $this->hospitalId,
                'patient_id'  => $this->patientId,
                'channel'     => Channel::WhatsApp,
                'type'        => $this->notificationType ?? $this->messageType,
                'content'     => ['recipient' => $this->phone, 'body' => $this->content],
                'status'      => $hasError ? 'failed' : 'sent',
                'external_id' => $externalId,
                'error'       => $error ?? ($result['_exception'] ?? null),
                'sent_at'     => $hasError ? null : now(),
            ]);
        } catch (\Throwable $e) {
            // Logging failure should not break message sending
            Log::warning('[MedOS][WhatsApp] Failed to log notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

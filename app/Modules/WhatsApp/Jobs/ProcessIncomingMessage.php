<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\NotificationLog;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
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
    public int $timeout = 120;

    /**
     * Exponential backoff in seconds.
     */
    public function backoff(): array
    {
        return [5, 15, 45];
    }

    /**
     * The fallback message sent when processing fails entirely.
     */
    private const FALLBACK_MESSAGE = "We're experiencing difficulties. A human agent will contact you shortly.";

    public function __construct(
        public readonly string $phone,
        public readonly array $messageContent,
        public readonly string $messageType,
        public readonly ?string $hospitalId,
        public readonly array $metadata = [],
    ) {
        $this->onQueue('whatsapp');
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        Log::info('[MedOS][WhatsApp] Processing incoming message', [
            'phone'       => $this->phone,
            'type'        => $this->messageType,
            'hospital_id' => $this->hospitalId,
            'message_id'  => $this->metadata['message_id'] ?? null,
        ]);

        try {
            // Mark the message as read (blue ticks)
            if (! empty($this->metadata['message_id'])) {
                $whatsAppService->markAsRead($this->metadata['message_id']);
            }

            // Log the incoming message
            $this->logIncomingMessage();

            // Resolve hospital context from the phone number mapping
            $hospitalId = $this->resolveHospitalId();

            if (! $hospitalId) {
                Log::warning('[MedOS][WhatsApp] Could not resolve hospital for incoming message', [
                    'phone' => $this->phone,
                ]);

                // Send a fallback message -- we cannot route without hospital context
                $this->sendFallback("We received your message but couldn't identify your hospital. Please contact us directly.");
                return;
            }

            // Handle media messages (image, document) -- download first
            $processedContent = $this->preprocessContent($whatsAppService);

            // Dispatch to the AI Receptionist ConversationManager
            // This is the main integration point with the AI module
            $conversationManager = app(\App\Modules\AIReceptionist\Services\ConversationManager::class);

            $responses = $conversationManager->handleIncomingMessage(
                phone: $this->phone,
                content: $processedContent,
                messageType: $this->messageType,
                channel: Channel::WhatsApp,
                hospitalId: $hospitalId,
                metadata: $this->metadata
            );

            // Send each response via the queue
            if (is_array($responses)) {
                foreach ($responses as $response) {
                    $this->sendResponse($response, $hospitalId);
                }
            }

        } catch (\Throwable $e) {
            Log::error('[MedOS][WhatsApp] Failed to process incoming message', [
                'phone'   => $this->phone,
                'type'    => $this->messageType,
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            // On final attempt, send the fallback message
            if ($this->attempts() >= $this->tries) {
                $this->sendFallback(self::FALLBACK_MESSAGE);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::critical('[MedOS][WhatsApp] Incoming message processing permanently failed', [
            'phone'      => $this->phone,
            'type'       => $this->messageType,
            'message_id' => $this->metadata['message_id'] ?? null,
            'error'      => $exception?->getMessage(),
        ]);
    }

    // ---------------------------------------------------------------
    // Internal Helpers
    // ---------------------------------------------------------------

    /**
     * Resolve the hospital ID from config, phone mapping, or existing conversations.
     */
    private function resolveHospitalId(): ?string
    {
        // If explicitly provided, use it
        if ($this->hospitalId) {
            return $this->hospitalId;
        }

        // Try to resolve from the receiving phone number ID (which phone number
        // in our system received this message -- each hospital has its own).
        $phoneNumberId = $this->metadata['phone_number_id'] ?? null;

        if ($phoneNumberId) {
            $hospital = Hospital::where('whatsapp_phone_number_id', $phoneNumberId)->first();

            if ($hospital) {
                return $hospital->id;
            }
        }

        // Fallback: check if there's an active conversation for this phone number
        $conversation = \App\Modules\AIReceptionist\Models\Conversation::where('external_chat_id', $this->phone)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($conversation) {
            return $conversation->hospital_id;
        }

        // Last resort: if there is only one hospital, use it
        if (Hospital::count() === 1) {
            return Hospital::first()?->id;
        }

        return null;
    }

    /**
     * Pre-process message content (e.g., download media for image/document messages).
     */
    private function preprocessContent(WhatsAppService $whatsAppService): array
    {
        $content = $this->messageContent;

        // Download images if a media_id is present
        if ($this->messageType === 'image' && ! empty($content['media_id'])) {
            try {
                $localPath = $whatsAppService->downloadMedia($content['media_id']);
                $content['local_path'] = $localPath;
            } catch (\Throwable $e) {
                Log::warning('[MedOS][WhatsApp] Failed to download image', [
                    'media_id' => $content['media_id'],
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // Download documents if a media_id is present
        if ($this->messageType === 'document' && ! empty($content['media_id'])) {
            try {
                $localPath = $whatsAppService->downloadMedia($content['media_id']);
                $content['local_path'] = $localPath;
            } catch (\Throwable $e) {
                Log::warning('[MedOS][WhatsApp] Failed to download document', [
                    'media_id' => $content['media_id'],
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $content;
    }

    /**
     * Send a response message via the SendWhatsAppMessage job.
     */
    private function sendResponse(array $response, string $hospitalId): void
    {
        $type = $response['type'] ?? 'text';
        $content = $response['content'] ?? $response;

        SendWhatsAppMessage::dispatch(
            phone: $this->phone,
            messageType: $type,
            content: is_string($content) ? ['text' => $content] : $content,
            hospitalId: $hospitalId,
            patientId: $response['patient_id'] ?? null,
            notificationType: $response['notification_type'] ?? 'ai_response',
        );
    }

    /**
     * Send a fallback message when processing fails.
     */
    private function sendFallback(string $message): void
    {
        try {
            SendWhatsAppMessage::dispatch(
                phone: $this->phone,
                messageType: 'text',
                content: ['text' => $message],
                hospitalId: $this->hospitalId,
                notificationType: 'fallback',
            );
        } catch (\Throwable $e) {
            Log::error('[MedOS][WhatsApp] Failed to send fallback message', [
                'phone' => $this->phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log the incoming message to notification_logs.
     */
    private function logIncomingMessage(): void
    {
        try {
            NotificationLog::create([
                'hospital_id' => $this->hospitalId,
                'channel'     => Channel::WhatsApp,
                'type'        => 'incoming_' . $this->messageType,
                'recipient'   => $this->phone,
                'content'     => $this->messageContent,
                'status'      => 'received',
                'external_id' => $this->metadata['message_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MedOS][WhatsApp] Failed to log incoming message', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

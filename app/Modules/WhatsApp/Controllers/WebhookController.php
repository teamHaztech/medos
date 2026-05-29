<?php

namespace App\Modules\WhatsApp\Controllers;

use App\Modules\Core\Models\NotificationLog;
use App\Modules\WhatsApp\Jobs\ProcessIncomingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    // ---------------------------------------------------------------
    // Webhook Verification (GET)
    // ---------------------------------------------------------------

    /**
     * Verify the webhook with Meta/Gupshup.
     *
     * Meta sends a GET request with hub.mode, hub.verify_token, and hub.challenge.
     * We must return the challenge value if the token matches.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = config('medos.whatsapp.meta_verify_token');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('[MedOS][WhatsApp] Webhook verified successfully');

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[MedOS][WhatsApp] Webhook verification failed', [
            'mode'  => $mode,
            'token' => $token ? '[REDACTED]' : null,
        ]);

        return response('Forbidden', 403);
    }

    // ---------------------------------------------------------------
    // Webhook Handler (POST)
    // ---------------------------------------------------------------

    /**
     * Handle incoming WhatsApp webhook events.
     *
     * Always returns 200 immediately. Heavy processing is dispatched
     * to queued jobs so Meta does not retry.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $provider = config('medos.whatsapp.provider', 'meta');

        try {
            $parsed = $provider === 'gupshup'
                ? $this->parseGupshupPayload($payload)
                : $this->parseMetaPayload($payload);

            if (empty($parsed)) {
                // Nothing actionable in this payload (e.g., system echo)
                return response()->json(['status' => 'ok']);
            }

            foreach ($parsed as $event) {
                $this->processEvent($event);
            }
        } catch (\Throwable $e) {
            // Log but never return non-200; Meta would retry
            Log::error('[MedOS][WhatsApp] Webhook processing error', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $this->sanitizePayload($payload),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    // ---------------------------------------------------------------
    // Payload Parsers
    // ---------------------------------------------------------------

    /**
     * Extract structured data from Meta Cloud API webhook format.
     *
     * Meta format: entry[].changes[].value.messages[]
     * Also handles: entry[].changes[].value.statuses[]
     */
    public function parseMetaPayload(array $payload): array
    {
        $events = [];

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // Determine the phone number ID receiving this message
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                // ---- Incoming Messages ----
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                foreach ($messages as $index => $message) {
                    // Skip echo / system messages
                    if (isset($message['context']['forwarded']) || ($message['type'] ?? '') === 'system') {
                        continue;
                    }

                    $contact = $contacts[$index] ?? ($contacts[0] ?? []);

                    $events[] = [
                        'event_type'      => 'message',
                        'provider'        => 'meta',
                        'phone_number_id' => $phoneNumberId,
                        'from'            => $message['from'] ?? null,
                        'from_name'       => $contact['profile']['name'] ?? null,
                        'message_id'      => $message['id'] ?? null,
                        'timestamp'       => $message['timestamp'] ?? null,
                        'type'            => $message['type'] ?? 'text',
                        'content'         => $this->extractMetaMessageContent($message),
                        'context'         => $message['context'] ?? null,
                    ];
                }

                // ---- Status Updates (sent, delivered, read) ----
                $statuses = $value['statuses'] ?? [];

                foreach ($statuses as $status) {
                    $events[] = [
                        'event_type'      => 'status',
                        'provider'        => 'meta',
                        'phone_number_id' => $phoneNumberId,
                        'message_id'      => $status['id'] ?? null,
                        'recipient'       => $status['recipient_id'] ?? null,
                        'status'          => $status['status'] ?? null,
                        'timestamp'       => $status['timestamp'] ?? null,
                        'errors'          => $status['errors'] ?? [],
                    ];
                }
            }
        }

        return $events;
    }

    /**
     * Extract structured data from Gupshup webhook format.
     */
    public function parseGupshupPayload(array $payload): array
    {
        $events = [];

        $type = $payload['type'] ?? null;

        if ($type === 'message') {
            $messagePayload = $payload['payload'] ?? [];
            $sender = $messagePayload['sender'] ?? [];
            $messageContent = $messagePayload['payload'] ?? [];

            $events[] = [
                'event_type'      => 'message',
                'provider'        => 'gupshup',
                'phone_number_id' => $messagePayload['destination'] ?? null,
                'from'            => $sender['phone'] ?? null,
                'from_name'       => $sender['name'] ?? null,
                'message_id'      => $messagePayload['id'] ?? null,
                'timestamp'       => $payload['timestamp'] ?? null,
                'type'            => $messageContent['type'] ?? 'text',
                'content'         => $this->extractGupshupMessageContent($messageContent),
                'context'         => $messagePayload['context'] ?? null,
            ];
        }

        if ($type === 'message-event') {
            $eventPayload = $payload['payload'] ?? [];

            $events[] = [
                'event_type'      => 'status',
                'provider'        => 'gupshup',
                'phone_number_id' => null,
                'message_id'      => $eventPayload['gsId'] ?? ($eventPayload['id'] ?? null),
                'recipient'       => $eventPayload['destination'] ?? null,
                'status'          => $eventPayload['type'] ?? null,
                'timestamp'       => $payload['timestamp'] ?? null,
                'errors'          => [],
            ];
        }

        return $events;
    }

    // ---------------------------------------------------------------
    // Internal Processing
    // ---------------------------------------------------------------

    /**
     * Route a parsed event to the appropriate handler.
     */
    private function processEvent(array $event): void
    {
        if ($event['event_type'] === 'status') {
            $this->handleStatusUpdate($event);
            return;
        }

        if ($event['event_type'] === 'message') {
            $this->handleIncomingMessage($event);
            return;
        }
    }

    /**
     * Handle an incoming message -- dispatch to a queued job.
     */
    private function handleIncomingMessage(array $event): void
    {
        $from = $event['from'] ?? null;
        $messageType = $event['type'] ?? 'text';
        $content = $event['content'] ?? [];

        if (! $from) {
            Log::warning('[MedOS][WhatsApp] Incoming message has no sender', $event);
            return;
        }

        Log::info('[MedOS][WhatsApp] Incoming message', [
            'from'       => $from,
            'type'       => $messageType,
            'message_id' => $event['message_id'] ?? null,
        ]);

        // Dispatch to job for async processing so we return 200 fast
        ProcessIncomingMessage::dispatch(
            phone: $from,
            messageContent: $content,
            messageType: $messageType,
            hospitalId: null, // Resolved by the job from phone mapping
            metadata: [
                'message_id'      => $event['message_id'] ?? null,
                'from_name'       => $event['from_name'] ?? null,
                'timestamp'       => $event['timestamp'] ?? null,
                'phone_number_id' => $event['phone_number_id'] ?? null,
                'context'         => $event['context'] ?? null,
                'provider'        => $event['provider'] ?? 'meta',
            ]
        );
    }

    /**
     * Handle status updates (sent, delivered, read) -- update NotificationLog.
     */
    private function handleStatusUpdate(array $event): void
    {
        $messageId = $event['message_id'] ?? null;
        $status = $event['status'] ?? null;

        if (! $messageId || ! $status) {
            return;
        }

        Log::debug('[MedOS][WhatsApp] Status update', [
            'message_id' => $messageId,
            'status'     => $status,
        ]);

        try {
            $notificationLog = NotificationLog::where('external_id', $messageId)->first();

            if (! $notificationLog) {
                return;
            }

            $now = now();

            switch ($status) {
                case 'sent':
                    $notificationLog->update([
                        'status'  => 'sent',
                        'sent_at' => $notificationLog->sent_at ?? $now,
                    ]);
                    break;

                case 'delivered':
                    $notificationLog->update([
                        'status'       => 'delivered',
                        'delivered_at' => $now,
                    ]);
                    break;

                case 'read':
                    $notificationLog->update([
                        'status'       => 'read',
                        'delivered_at' => $notificationLog->delivered_at ?? $now,
                    ]);
                    break;

                case 'failed':
                    $errors = $event['errors'] ?? [];
                    $errorMessage = ! empty($errors)
                        ? json_encode($errors)
                        : 'Unknown delivery failure';

                    $notificationLog->update([
                        'status' => 'failed',
                        'error'  => $errorMessage,
                    ]);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('[MedOS][WhatsApp] Failed to update notification status', [
                'message_id' => $messageId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ---------------------------------------------------------------
    // Content Extraction Helpers
    // ---------------------------------------------------------------

    /**
     * Extract message content from a Meta message object.
     */
    private function extractMetaMessageContent(array $message): array
    {
        $type = $message['type'] ?? 'text';

        return match ($type) {
            'text' => [
                'text' => $message['text']['body'] ?? '',
            ],

            'image' => [
                'media_id' => $message['image']['id'] ?? null,
                'mime_type' => $message['image']['mime_type'] ?? null,
                'caption'  => $message['image']['caption'] ?? null,
            ],

            'document' => [
                'media_id' => $message['document']['id'] ?? null,
                'mime_type' => $message['document']['mime_type'] ?? null,
                'filename' => $message['document']['filename'] ?? null,
                'caption'  => $message['document']['caption'] ?? null,
            ],

            'location' => [
                'latitude'  => $message['location']['latitude'] ?? null,
                'longitude' => $message['location']['longitude'] ?? null,
                'name'      => $message['location']['name'] ?? null,
                'address'   => $message['location']['address'] ?? null,
            ],

            'interactive' => $this->extractInteractiveContent($message['interactive'] ?? []),

            'button' => [
                'text'    => $message['button']['text'] ?? '',
                'payload' => $message['button']['payload'] ?? '',
            ],

            'audio' => [
                'media_id'  => $message['audio']['id'] ?? null,
                'mime_type' => $message['audio']['mime_type'] ?? null,
            ],

            'video' => [
                'media_id'  => $message['video']['id'] ?? null,
                'mime_type' => $message['video']['mime_type'] ?? null,
                'caption'   => $message['video']['caption'] ?? null,
            ],

            default => ['raw' => $message],
        };
    }

    /**
     * Extract content from an interactive reply (button or list).
     */
    private function extractInteractiveContent(array $interactive): array
    {
        $type = $interactive['type'] ?? null;

        if ($type === 'button_reply') {
            return [
                'interactive_type' => 'button_reply',
                'button_id'        => $interactive['button_reply']['id'] ?? null,
                'button_title'     => $interactive['button_reply']['title'] ?? null,
            ];
        }

        if ($type === 'list_reply') {
            return [
                'interactive_type' => 'list_reply',
                'list_id'          => $interactive['list_reply']['id'] ?? null,
                'list_title'       => $interactive['list_reply']['title'] ?? null,
                'list_description' => $interactive['list_reply']['description'] ?? null,
            ];
        }

        return ['interactive_type' => $type, 'raw' => $interactive];
    }

    /**
     * Extract message content from a Gupshup message object.
     */
    private function extractGupshupMessageContent(array $payload): array
    {
        $type = $payload['type'] ?? 'text';

        return match ($type) {
            'text' => ['text' => $payload['text'] ?? ($payload['payload']['text'] ?? '')],
            'image' => [
                'media_url' => $payload['url'] ?? null,
                'caption'   => $payload['caption'] ?? null,
            ],
            'document' => [
                'media_url' => $payload['url'] ?? null,
                'filename'  => $payload['filename'] ?? null,
                'caption'   => $payload['caption'] ?? null,
            ],
            'location' => [
                'latitude'  => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
            ],
            default => ['raw' => $payload],
        };
    }

    /**
     * Sanitize webhook payload for safe logging (remove overly large fields).
     */
    private function sanitizePayload(array $payload): array
    {
        // Truncate for safety
        $json = json_encode($payload);
        if (strlen($json) > 5000) {
            return ['_truncated' => true, '_size' => strlen($json)];
        }

        return $payload;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * GET — Meta webhook verification.
     * Meta sends: hub.mode=subscribe, hub.verify_token=YOUR_TOKEN, hub.challenge=RANDOM
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('medos.whatsapp.meta_verify_token');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('[WhatsApp] Webhook verified successfully');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[WhatsApp] Webhook verification failed', ['token' => $token]);
        return response('Forbidden', 403);
    }

    /**
     * POST — Incoming messages from Meta WhatsApp Cloud API.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        // Always return 200 immediately (Meta requirement)
        // Process async
        try {
            $this->processPayload($payload);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] Error processing message', ['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    private function processPayload(array $payload)
    {
        // Extract messages from Meta payload format
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // Skip status updates (sent, delivered, read)
                if (isset($value['statuses'])) continue;

                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                foreach ($messages as $message) {
                    $from = $message['from'] ?? null; // Phone number (e.g., 919876543210)
                    $msgType = $message['type'] ?? 'text';
                    $msgId = $message['id'] ?? null;
                    $timestamp = $message['timestamp'] ?? null;

                    // Get contact name
                    $contactName = null;
                    foreach ($contacts as $contact) {
                        if (($contact['wa_id'] ?? '') === $from) {
                            $contactName = $contact['profile']['name'] ?? null;
                        }
                    }

                    // Extract text content
                    $text = '';
                    switch ($msgType) {
                        case 'text':
                            $text = $message['text']['body'] ?? '';
                            break;
                        case 'interactive':
                            // Button reply or list reply
                            $text = $message['interactive']['button_reply']['title']
                                ?? $message['interactive']['list_reply']['title']
                                ?? '';
                            break;
                        default:
                            $text = "[$msgType message received]";
                    }

                    if (!$from || !$text) continue;

                    Log::info('[WhatsApp] Message received', [
                        'from' => $from, 'text' => $text, 'type' => $msgType,
                    ]);

                    // Mark as read
                    $this->markAsRead($msgId);

                    // Process through bot engine (same as chat simulator)
                    $this->processBotMessage($from, $text);
                }
            }
        }
    }

    private function processBotMessage(string $phone, string $text)
    {
        // Normalize phone: 919876543210 → 9876543210
        $cleanPhone = preg_replace('/^91/', '', $phone);
        if (strlen($cleanPhone) > 10) $cleanPhone = substr($cleanPhone, -10);

        $sessionId = 'wa_' . $cleanPhone;

        // Use the same ChatController logic
        $chatRequest = new \Illuminate\Http\Request();
        $chatRequest->merge([
            'message' => $text,
            'phone' => $cleanPhone,
            'session_id' => $sessionId,
        ]);

        $chatController = app(ChatController::class);
        $response = $chatController->send($chatRequest);
        $data = json_decode($response->getContent(), true);

        // Send each reply back via WhatsApp API
        foreach ($data['replies'] ?? [] as $reply) {
            $this->sendMessage($phone, $reply['text']);
            usleep(500000); // 0.5s delay between messages
        }
    }

    /**
     * Send a text message via Meta WhatsApp Cloud API.
     */
    private function sendMessage(string $to, string $text)
    {
        $token = config('medos.whatsapp.meta_token');
        $phoneNumberId = config('medos.whatsapp.meta_phone_number_id');

        if (!$token || !$phoneNumberId) {
            Log::warning('[WhatsApp] Cannot send — Meta token or phone number ID not configured');
            return;
        }

        try {
            $response = Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            if (!$response->successful()) {
                Log::error('[WhatsApp] Send failed', [
                    'to' => $to, 'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] Send exception', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Mark message as read (blue ticks).
     */
    private function markAsRead(?string $messageId)
    {
        if (!$messageId) return;

        $token = config('medos.whatsapp.meta_token');
        $phoneNumberId = config('medos.whatsapp.meta_phone_number_id');

        if (!$token || !$phoneNumberId) return;

        try {
            Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);
        } catch (\Throwable $e) {}
    }
}

<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService extends BaseModuleService
{
    /**
     * Meta Graph API base URL.
     */
    private const META_API_BASE = 'https://graph.facebook.com/v18.0';

    /**
     * Gupshup API base URL.
     */
    private const GUPSHUP_API_BASE = 'https://api.gupshup.io/wa/api/v1';

    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    // ---------------------------------------------------------------
    // Public Methods
    // ---------------------------------------------------------------

    /**
     * Send a plain text message.
     */
    public function sendTextMessage(string $to, string $text): array
    {
        $to = $this->formatPhoneNumber($to);

        $this->logInfo('Sending text message', ['to' => $to]);

        if ($this->getProvider() === 'gupshup') {
            return $this->callGupshupApi('/msg', [
                'channel'     => 'whatsapp',
                'source'      => config('medos.whatsapp.meta_phone_number_id'),
                'destination' => $to,
                'message'     => json_encode([
                    'type' => 'text',
                    'text' => $text,
                ]),
            ]);
        }

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text,
            ],
        ]);
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode, array $components = []): array
    {
        $to = $this->formatPhoneNumber($to);

        $this->logInfo('Sending template message', [
            'to'       => $to,
            'template' => $templateName,
        ]);

        if ($this->getProvider() === 'gupshup') {
            return $this->callGupshupApi('/msg', [
                'channel'     => 'whatsapp',
                'source'      => config('medos.whatsapp.meta_phone_number_id'),
                'destination' => $to,
                'message'     => json_encode([
                    'type'     => 'template',
                    'template' => [
                        'name'     => $templateName,
                        'language' => ['code' => $languageCode],
                        'components' => $components,
                    ],
                ]),
            ]);
        }

        $templatePayload = [
            'name'     => $templateName,
            'language' => ['code' => $languageCode],
        ];

        if (! empty($components)) {
            $templatePayload['components'] = $components;
        }

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $templatePayload,
        ]);
    }

    /**
     * Send interactive buttons (max 3 buttons, each has id + title).
     */
    public function sendInteractiveButtons(string $to, string $body, array $buttons): array
    {
        $to = $this->formatPhoneNumber($to);

        // Enforce Meta's 3-button limit
        $buttons = array_slice($buttons, 0, 3);

        $formattedButtons = array_map(function (array $button) {
            return [
                'type'  => 'reply',
                'reply' => [
                    'id'    => $button['id'],
                    'title' => mb_substr($button['title'], 0, 20), // Meta limit: 20 chars
                ],
            ];
        }, $buttons);

        $this->logInfo('Sending interactive buttons', ['to' => $to, 'button_count' => count($formattedButtons)]);

        if ($this->getProvider() === 'gupshup') {
            return $this->callGupshupApi('/msg', [
                'channel'     => 'whatsapp',
                'source'      => config('medos.whatsapp.meta_phone_number_id'),
                'destination' => $to,
                'message'     => json_encode([
                    'type'        => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => ['text' => $body],
                        'action' => ['buttons' => $formattedButtons],
                    ],
                ]),
            ]);
        }

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'button',
                'body'   => ['text' => $body],
                'action' => [
                    'buttons' => $formattedButtons,
                ],
            ],
        ]);
    }

    /**
     * Send an interactive list message (sections with rows).
     */
    public function sendInteractiveList(string $to, string $body, string $buttonText, array $sections): array
    {
        $to = $this->formatPhoneNumber($to);

        $this->logInfo('Sending interactive list', ['to' => $to, 'section_count' => count($sections)]);

        if ($this->getProvider() === 'gupshup') {
            return $this->callGupshupApi('/msg', [
                'channel'     => 'whatsapp',
                'source'      => config('medos.whatsapp.meta_phone_number_id'),
                'destination' => $to,
                'message'     => json_encode([
                    'type'        => 'interactive',
                    'interactive' => [
                        'type'   => 'list',
                        'body'   => ['text' => $body],
                        'action' => [
                            'button'   => $buttonText,
                            'sections' => $sections,
                        ],
                    ],
                ]),
            ]);
        }

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'list',
                'body'   => ['text' => $body],
                'action' => [
                    'button'   => $buttonText,
                    'sections' => $sections,
                ],
            ],
        ]);
    }

    /**
     * Send a location request message.
     */
    public function sendLocationRequest(string $to, string $body): array
    {
        $to = $this->formatPhoneNumber($to);

        $this->logInfo('Sending location request', ['to' => $to]);

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'location_request_message',
                'body'   => ['text' => $body],
                'action' => [
                    'name' => 'send_location',
                ],
            ],
        ]);
    }

    /**
     * Send a document message.
     */
    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null): array
    {
        $to = $this->formatPhoneNumber($to);

        $this->logInfo('Sending document', ['to' => $to, 'filename' => $filename]);

        $document = [
            'link'     => $documentUrl,
            'filename' => $filename,
        ];

        if ($caption !== null) {
            $document['caption'] = $caption;
        }

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'document',
            'document'          => $document,
        ]);
    }

    /**
     * Send an image message.
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null): array
    {
        $to = $this->formatPhoneNumber($to);

        $this->logInfo('Sending image', ['to' => $to]);

        $image = ['link' => $imageUrl];

        if ($caption !== null) {
            $image['caption'] = $caption;
        }

        return $this->callMetaApi('/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'image',
            'image'             => $image,
        ]);
    }

    /**
     * Mark a message as read (blue ticks).
     */
    public function markAsRead(string $messageId): void
    {
        try {
            $this->callMetaApi('/messages', [
                'messaging_product' => 'whatsapp',
                'status'            => 'read',
                'message_id'        => $messageId,
            ]);
        } catch (\Throwable $e) {
            // Non-critical -- log and continue
            $this->logWarning('Failed to mark message as read', [
                'message_id' => $messageId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Download a media file and return its local storage path.
     */
    public function downloadMedia(string $mediaId): string
    {
        $this->logInfo('Downloading media', ['media_id' => $mediaId]);

        try {
            // Step 1: Get the media URL from Meta
            $metaToken = config('medos.whatsapp.meta_token');

            $mediaInfoResponse = Http::withToken($metaToken)
                ->timeout(30)
                ->get(self::META_API_BASE . '/' . $mediaId);

            if (! $mediaInfoResponse->successful()) {
                throw new \RuntimeException('Failed to retrieve media info: ' . $mediaInfoResponse->body());
            }

            $mediaUrl = $mediaInfoResponse->json('url');

            if (! $mediaUrl) {
                throw new \RuntimeException('No URL returned for media ID: ' . $mediaId);
            }

            // Step 2: Download the actual media file
            $fileResponse = Http::withToken($metaToken)
                ->timeout(60)
                ->get($mediaUrl);

            if (! $fileResponse->successful()) {
                throw new \RuntimeException('Failed to download media file: ' . $fileResponse->status());
            }

            // Step 3: Store locally
            $contentType = $fileResponse->header('Content-Type');
            $extension = $this->mimeToExtension($contentType);
            $filename = 'whatsapp_media/' . date('Y/m/d') . '/' . $mediaId . '.' . $extension;

            Storage::disk('local')->put($filename, $fileResponse->body());

            $this->logInfo('Media downloaded', ['path' => $filename]);

            return Storage::disk('local')->path($filename);
        } catch (\Throwable $e) {
            $this->logError('Media download failed', [
                'media_id' => $mediaId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ---------------------------------------------------------------
    // Private Methods
    // ---------------------------------------------------------------

    /**
     * HTTP POST to Meta Graph API.
     */
    private function callMetaApi(string $endpoint, array $data): array
    {
        $phoneNumberId = config('medos.whatsapp.meta_phone_number_id');
        $token = config('medos.whatsapp.meta_token');
        $url = self::META_API_BASE . '/' . $phoneNumberId . $endpoint;

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->retry(2, 1000, function (\Exception $exception) {
                    // Retry on 5xx or connection errors, not on 4xx
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        return $exception->response->status() >= 500;
                    }
                    return true;
                })
                ->post($url, $data);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                $this->logError('Meta API error', [
                    'url'      => $url,
                    'status'   => $response->status(),
                    'response' => $body,
                ]);

                return array_merge($body, ['_error' => true, '_status' => $response->status()]);
            }

            return $body;
        } catch (\Throwable $e) {
            $this->logError('Meta API exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return ['_error' => true, '_exception' => $e->getMessage()];
        }
    }

    /**
     * HTTP POST to Gupshup API.
     */
    private function callGupshupApi(string $endpoint, array $data): array
    {
        $apiKey = config('medos.whatsapp.gupshup_api_key');
        $url = self::GUPSHUP_API_BASE . $endpoint;

        try {
            $response = Http::withHeaders([
                    'apikey'       => $apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->timeout(30)
                ->asForm()
                ->post($url, $data);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                $this->logError('Gupshup API error', [
                    'url'      => $url,
                    'status'   => $response->status(),
                    'response' => $body,
                ]);

                return array_merge($body, ['_error' => true, '_status' => $response->status()]);
            }

            return $body;
        } catch (\Throwable $e) {
            $this->logError('Gupshup API exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return ['_error' => true, '_exception' => $e->getMessage()];
        }
    }

    /**
     * Get the configured WhatsApp provider.
     */
    private function getProvider(): string
    {
        return config('medos.whatsapp.provider', 'meta');
    }

    /**
     * Ensure proper phone number format with country code.
     *
     * Handles:
     * - Stripping spaces, dashes, parentheses
     * - Ensuring + prefix
     * - Indian 10-digit numbers get +91 prefix
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Strip non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove leading + for processing
        $hasPlus = str_starts_with($phone, '+');
        $digits = ltrim($phone, '+');

        // Indian 10-digit number (starts with 6, 7, 8, or 9)
        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            return '91' . $digits;
        }

        // Already has country code (11+ digits or starts with known codes)
        if (strlen($digits) >= 11) {
            return $digits;
        }

        // Fallback: return as-is (digits only, no +, per Meta API requirement)
        return $digits;
    }

    /**
     * Map MIME type to file extension.
     */
    private function mimeToExtension(?string $mime): string
    {
        $map = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'video/mp4'       => 'mp4',
            'audio/ogg'       => 'ogg',
            'audio/mpeg'      => 'mp3',
            'audio/aac'       => 'aac',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $map[$mime] ?? 'bin';
    }
}

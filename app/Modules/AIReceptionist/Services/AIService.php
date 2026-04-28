<?php

namespace App\Modules\AIReceptionist\Services;

use App\Modules\AIReceptionist\Enums\Intent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private string $provider;
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->provider    = config('medos.ai.provider', 'anthropic');
        $this->maxTokens   = (int) config('medos.ai.max_tokens', 2048);
        $this->temperature = (float) config('medos.ai.temperature', 0.3);

        if ($this->provider === 'anthropic') {
            $this->apiKey = config('medos.ai.anthropic_key', '');
            $this->model  = config('medos.ai.anthropic_model', 'claude-sonnet-4-20250514');
        } else {
            $this->apiKey = config('medos.ai.openai_key', '');
            $this->model  = config('medos.ai.openai_model', 'gpt-4o');
        }
    }

    // -----------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------

    /**
     * Send a chat request to the configured AI provider.
     *
     * @param  string  $systemPrompt  The system-level instruction.
     * @param  array   $messages      Array of {role, content} message objects.
     * @param  array   $tools         Optional tool/function definitions.
     * @return array   The parsed response: ['content' => string, 'tool_calls' => array, 'raw' => array]
     */
    public function chat(string $systemPrompt, array $messages, array $tools = []): array
    {
        Log::debug('[AIService] chat request', [
            'provider'      => $this->provider,
            'model'         => $this->model,
            'message_count' => count($messages),
            'has_tools'     => !empty($tools),
        ]);

        try {
            $response = $this->provider === 'anthropic'
                ? $this->callAnthropic($systemPrompt, $messages, $tools)
                : $this->callOpenAI($systemPrompt, $messages, $tools);

            Log::debug('[AIService] chat response received', [
                'content_length' => strlen($response['content'] ?? ''),
                'tool_calls'     => count($response['tool_calls'] ?? []),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('[AIService] chat failed', [
                'error'    => $e->getMessage(),
                'provider' => $this->provider,
            ]);

            throw $e;
        }
    }

    /**
     * Detect the language of the given text.
     *
     * @return array{language: string, confidence: float}
     */
    public function detectLanguage(string $text): array
    {
        $systemPrompt = <<<'PROMPT'
You are a language detection service. Analyse the user text and respond with ONLY a JSON object, no additional text:
{"language": "<ISO 639-1 code>", "confidence": <0.0-1.0>}
Supported languages: en, hi, ar, ta, te, kn, bn, mr.
If uncertain, default to "en" with low confidence.
PROMPT;

        try {
            $response = $this->chat($systemPrompt, [
                ['role' => 'user', 'content' => $text],
            ]);

            $json = $this->extractJson($response['content']);

            if ($json && isset($json['language'], $json['confidence'])) {
                return [
                    'language'   => $json['language'],
                    'confidence' => (float) $json['confidence'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[AIService] language detection failed, defaulting to en', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['language' => 'en', 'confidence' => 0.5];
    }

    /**
     * Classify the user's intent from a message.
     */
    public function extractIntent(string $message, array $context = []): Intent
    {
        $validIntents = implode(', ', array_map(fn (Intent $i) => $i->value, Intent::cases()));

        $contextBlock = !empty($context)
            ? "Conversation context:\n" . json_encode($context, JSON_PRETTY_PRINT)
            : '';

        $systemPrompt = <<<PROMPT
You are an intent classifier for a hospital receptionist system.
Classify the patient message into exactly ONE intent. Respond with ONLY a JSON object:
{"intent": "<intent_value>"}

Valid intents: {$validIntents}

{$contextBlock}
PROMPT;

        try {
            $response = $this->chat($systemPrompt, [
                ['role' => 'user', 'content' => $message],
            ]);

            $json = $this->extractJson($response['content']);

            if ($json && isset($json['intent'])) {
                $intent = Intent::tryFrom($json['intent']);

                if ($intent !== null) {
                    return $intent;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[AIService] intent extraction failed, defaulting to GeneralInquiry', [
                'error' => $e->getMessage(),
            ]);
        }

        return Intent::GeneralInquiry;
    }

    /**
     * Extract structured intake data from conversation messages.
     *
     * @return array The structured intake data (chief_complaint, duration, severity, etc.)
     */
    public function extractIntakeData(array $conversationMessages): array
    {
        $systemPrompt = <<<'PROMPT'
You are a medical intake data extractor. Analyse the conversation and extract structured patient intake data.
Respond with ONLY a JSON object containing the following fields (use null for unknown fields):
{
    "chief_complaint": "string - main reason for visit",
    "duration": "string - how long symptoms have been present",
    "severity": "string - mild/moderate/severe based on description",
    "patient_type": "string - self/other (is patient booking for themselves or someone else)",
    "relevant_history": "string - any mentioned medical history or conditions",
    "symptoms": ["array of specific symptoms mentioned"],
    "allergies_mentioned": ["array of any allergies mentioned"],
    "current_medications_mentioned": ["array of any medications mentioned"],
    "is_complete": true/false
}
Set is_complete to true ONLY if chief_complaint is clearly identified.
PROMPT;

        try {
            $response = $this->chat($systemPrompt, $conversationMessages);
            $json = $this->extractJson($response['content']);

            if ($json) {
                return $json;
            }
        } catch (\Throwable $e) {
            Log::warning('[AIService] intake extraction failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'chief_complaint' => null,
            'is_complete'     => false,
        ];
    }

    // -----------------------------------------------------------------
    // Provider-Specific Methods
    // -----------------------------------------------------------------

    /**
     * Call the Anthropic Messages API.
     */
    private function callAnthropic(string $systemPrompt, array $messages, array $tools = []): array
    {
        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $systemPrompt,
            'messages'   => $this->formatAnthropicMessages($messages),
        ];

        if (!empty($tools)) {
            $body['tools'] = $this->formatAnthropicTools($tools);
        }

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', $body);

        if ($response->failed()) {
            Log::error('[AIService] Anthropic API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("Anthropic API returned status {$response->status()}");
        }

        $data = $response->json();

        return $this->parseAnthropicResponse($data);
    }

    /**
     * Call the OpenAI Chat Completions API.
     */
    private function callOpenAI(string $systemPrompt, array $messages, array $tools = []): array
    {
        $formattedMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $this->formatOpenAIMessages($messages),
        );

        $body = [
            'model'       => $this->model,
            'max_tokens'  => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages'    => $formattedMessages,
        ];

        if (!empty($tools)) {
            $body['tools'] = $this->formatOpenAITools($tools);
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ])
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', $body);

        if ($response->failed()) {
            Log::error('[AIService] OpenAI API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("OpenAI API returned status {$response->status()}");
        }

        $data = $response->json();

        return $this->parseOpenAIResponse($data);
    }

    // -----------------------------------------------------------------
    // Message Formatting
    // -----------------------------------------------------------------

    /**
     * Format messages for the Anthropic API.
     */
    private function formatAnthropicMessages(array $messages): array
    {
        return array_map(function (array $msg) {
            return [
                'role'    => $msg['role'] === 'system' ? 'user' : $msg['role'],
                'content' => $msg['content'],
            ];
        }, array_values(array_filter($messages, fn ($m) => $m['role'] !== 'system')));
    }

    /**
     * Format messages for the OpenAI API.
     */
    private function formatOpenAIMessages(array $messages): array
    {
        return array_map(fn (array $msg) => [
            'role'    => $msg['role'],
            'content' => $msg['content'],
        ], $messages);
    }

    /**
     * Format tools for the Anthropic API.
     */
    private function formatAnthropicTools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'name'         => $tool['name'],
            'description'  => $tool['description'] ?? '',
            'input_schema' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
        ], $tools);
    }

    /**
     * Format tools for the OpenAI API.
     */
    private function formatOpenAITools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters'  => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ], $tools);
    }

    // -----------------------------------------------------------------
    // Response Parsing
    // -----------------------------------------------------------------

    /**
     * Parse an Anthropic API response into a normalised structure.
     */
    private function parseAnthropicResponse(array $data): array
    {
        $content    = '';
        $toolCalls  = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id'    => $block['id'],
                    'name'  => $block['name'],
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return [
            'content'    => $content,
            'tool_calls' => $toolCalls,
            'raw'        => $data,
        ];
    }

    /**
     * Parse an OpenAI API response into a normalised structure.
     */
    private function parseOpenAIResponse(array $data): array
    {
        $choice    = $data['choices'][0] ?? [];
        $message   = $choice['message'] ?? [];
        $content   = $message['content'] ?? '';
        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = [
                'id'    => $tc['id'],
                'name'  => $tc['function']['name'],
                'input' => json_decode($tc['function']['arguments'] ?? '{}', true),
            ];
        }

        return [
            'content'    => $content,
            'tool_calls' => $toolCalls,
            'raw'        => $data,
        ];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Extract a JSON object from a string that may contain surrounding text.
     */
    private function extractJson(string $text): ?array
    {
        // Try direct decode first
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try to find JSON within markdown code blocks
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try to find a raw JSON object in the text
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

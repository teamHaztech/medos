<?php

namespace App\Modules\AIReceptionist\Handlers;

use App\Modules\AIReceptionist\Models\Conversation;
use App\Modules\AIReceptionist\Prompts\SystemPrompts;
use App\Modules\AIReceptionist\Services\AIService;
use App\Modules\Core\Models\Hospital;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Facades\Log;

class IntakeHandler
{
    public function __construct(
        private readonly AIService $aiService,
    ) {}

    /**
     * Handle a message during the Intake conversation state.
     *
     * @return array{response: string, intake_data: ?array, is_complete: bool}
     */
    public function handle(Conversation $conversation, string $message): array
    {
        $hospital = $conversation->hospital ?? Hospital::find($conversation->hospital_id);
        $patient  = $conversation->patient ?? Patient::find($conversation->patient_id);

        $context  = $this->buildIntakeContext($conversation);

        $systemPrompt = SystemPrompts::intake($hospital, $patient, $context);

        // Build the AI message history from conversation
        $aiMessages = $this->buildAIMessages($conversation, $message);

        try {
            $aiResponse = $this->aiService->chat($systemPrompt, $aiMessages);
            $responseText = $aiResponse['content'];
        } catch (\Throwable $e) {
            Log::error('[IntakeHandler] AI call failed', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);

            return [
                'response'    => 'I apologize, I\'m having a brief technical issue. Could you please repeat what you were saying?',
                'intake_data' => null,
                'is_complete' => false,
            ];
        }

        // Try to parse structured intake data from the AI response
        $intakeData = $this->parseIntakeFromAI($responseText);
        $isComplete = $intakeData !== null && $this->isIntakeComplete($intakeData);

        // Strip JSON block from patient-facing response
        $cleanResponse = $this->stripJsonBlock($responseText);

        Log::info('[IntakeHandler] processed message', [
            'conversation_id' => $conversation->id,
            'has_intake_data' => $intakeData !== null,
            'is_complete'     => $isComplete,
        ]);

        return [
            'response'    => $cleanResponse,
            'intake_data' => $intakeData,
            'is_complete' => $isComplete,
        ];
    }

    /**
     * Check if the extracted intake data has enough fields to proceed.
     *
     * Chief complaint is required. Duration is nice-to-have.
     */
    public function isIntakeComplete(array $intakeData): bool
    {
        // Chief complaint is the absolute minimum
        if (empty($intakeData['chief_complaint'])) {
            return false;
        }

        // If the AI itself flagged it as complete, trust it
        if (!empty($intakeData['is_complete'])) {
            return true;
        }

        // If we have chief complaint, consider it minimally complete
        return true;
    }

    /**
     * Extract structured intake JSON from an AI response string.
     *
     * Looks for a JSON code block in the response.
     */
    public function parseIntakeFromAI(string $aiResponse): ?array
    {
        // Look for JSON in markdown code block
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $aiResponse, $matches)) {
            $decoded = json_decode($matches[1], true);

            if (is_array($decoded) && isset($decoded['chief_complaint'])) {
                return $decoded;
            }
        }

        // Try to find a raw JSON object containing chief_complaint
        if (preg_match('/\{[^{}]*"chief_complaint"[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $aiResponse, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Build context from the conversation history for the AI.
     *
     * Extracts any previously collected intake data and summarises the conversation state.
     */
    public function buildIntakeContext(Conversation $conversation): array
    {
        $messages = $conversation->messages ?? [];
        $context  = [
            'message_count'     => count($messages),
            'language'          => $conversation->language_detected ?? 'en',
            'collected_so_far'  => [],
        ];

        // Check AI reasoning trace for previously extracted data
        $trace = $conversation->ai_reasoning_trace ?? [];

        if (!empty($trace['intake_data'])) {
            $context['collected_so_far'] = $trace['intake_data'];
        }

        return $context;
    }

    /**
     * Build the AI message array from conversation history plus the new message.
     */
    private function buildAIMessages(Conversation $conversation, string $currentMessage): array
    {
        $aiMessages = [];
        $messages   = $conversation->messages ?? [];

        foreach ($messages as $msg) {
            $role = match ($msg['role'] ?? '') {
                'patient'   => 'user',
                'assistant' => 'assistant',
                default     => null,
            };

            if ($role !== null) {
                $aiMessages[] = [
                    'role'    => $role,
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        // Add the current incoming message
        $aiMessages[] = [
            'role'    => 'user',
            'content' => $currentMessage,
        ];

        return $aiMessages;
    }

    /**
     * Strip JSON code blocks from the response text so the patient sees clean text.
     */
    private function stripJsonBlock(string $text): string
    {
        $cleaned = preg_replace('/```(?:json)?\s*\{[\s\S]*?\}\s*```/', '', $text);

        return trim($cleaned);
    }
}

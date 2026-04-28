<?php

namespace App\Modules\WhatsApp\Templates;

use Illuminate\Support\Facades\Log;

class TemplateManager
{
    /**
     * Predefined WhatsApp message templates.
     *
     * Each template has:
     * - name: The template name registered with Meta/Gupshup
     * - body: The message body with {variable} placeholders
     * - variables: List of required variable names
     * - category: Meta template category (UTILITY, MARKETING, AUTHENTICATION)
     */
    private const TEMPLATES = [
        'appointment_confirmation' => [
            'name'      => 'appointment_confirmation',
            'body'      => 'Your appointment with {doctor} is confirmed for {date} at {time}. Token: {token}. Location: {location}',
            'variables' => ['doctor', 'date', 'time', 'token', 'location'],
            'category'  => 'UTILITY',
        ],

        'appointment_reminder' => [
            'name'      => 'appointment_reminder',
            'body'      => 'Reminder: Your appointment with {doctor} is tomorrow at {time}.',
            'variables' => ['doctor', 'time'],
            'category'  => 'UTILITY',
        ],

        'queue_update' => [
            'name'      => 'queue_update',
            'body'      => 'You are now #{position} in queue. Estimated wait: {minutes} minutes.',
            'variables' => ['position', 'minutes'],
            'category'  => 'UTILITY',
        ],

        'lab_results_ready' => [
            'name'      => 'lab_results_ready',
            'body'      => 'Your lab results are ready. Dr. {doctor} will review them shortly.',
            'variables' => ['doctor'],
            'category'  => 'UTILITY',
        ],

        'payment_link' => [
            'name'      => 'payment_link',
            'body'      => 'Your bill: {amount}. Insurance covers: {insurance_amount}. Pay {payable}: {payment_link}',
            'variables' => ['amount', 'insurance_amount', 'payable', 'payment_link'],
            'category'  => 'UTILITY',
        ],

        'feedback_request' => [
            'name'      => 'feedback_request',
            'body'      => 'How was your visit today? Reply 1-5 (1=Poor, 5=Excellent)',
            'variables' => [],
            'category'  => 'MARKETING',
        ],

        'welcome_back' => [
            'name'      => 'welcome_back',
            'body'      => 'Welcome back, {name}! Book with {doctor} for {condition} follow-up? Reply YES or tap below.',
            'variables' => ['name', 'doctor', 'condition'],
            'category'  => 'MARKETING',
        ],
    ];

    // ---------------------------------------------------------------
    // Public Methods
    // ---------------------------------------------------------------

    /**
     * Get the raw template structure by name.
     *
     * @throws \InvalidArgumentException If the template does not exist.
     */
    public function getTemplate(string $name): array
    {
        if (! isset(self::TEMPLATES[$name])) {
            throw new \InvalidArgumentException("WhatsApp template not found: {$name}");
        }

        return self::TEMPLATES[$name];
    }

    /**
     * Render a template by filling in variable placeholders.
     *
     * Returns the rendered text body and the Meta API components array
     * suitable for passing to WhatsAppService::sendTemplateMessage().
     *
     * @return array{body: string, components: array, template_name: string, language_code: string}
     */
    public function renderTemplate(string $name, array $variables, string $languageCode = 'en'): array
    {
        $template = $this->getTemplate($name);

        // Render the body with variable substitution
        $body = $template['body'];
        foreach ($variables as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        // Validate all required variables are provided
        $missing = $this->getMissingVariables($template, $variables);
        if (! empty($missing)) {
            Log::warning('[MedOS][WhatsApp] Template rendered with missing variables', [
                'template' => $name,
                'missing'  => $missing,
            ]);
        }

        // Build Meta Cloud API components array
        $components = $this->buildMetaComponents($template, $variables);

        return [
            'body'          => $body,
            'components'    => $components,
            'template_name' => $template['name'],
            'language_code' => $languageCode,
        ];
    }

    /**
     * Get all available template names.
     */
    public function getAvailableTemplates(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * Check if a template exists.
     */
    public function hasTemplate(string $name): bool
    {
        return isset(self::TEMPLATES[$name]);
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Identify any required variables that are missing from the provided values.
     */
    private function getMissingVariables(array $template, array $variables): array
    {
        $required = $template['variables'] ?? [];

        return array_values(array_diff($required, array_keys($variables)));
    }

    /**
     * Build Meta Cloud API template components from variables.
     *
     * Meta expects components in this format:
     * [
     *   {
     *     "type": "body",
     *     "parameters": [
     *       {"type": "text", "text": "value1"},
     *       {"type": "text", "text": "value2"}
     *     ]
     *   }
     * ]
     *
     * Variables are ordered as defined in the template's variables list.
     */
    private function buildMetaComponents(array $template, array $variables): array
    {
        $requiredVars = $template['variables'] ?? [];

        if (empty($requiredVars)) {
            return [];
        }

        $parameters = [];

        foreach ($requiredVars as $varName) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) ($variables[$varName] ?? ''),
            ];
        }

        return [
            [
                'type'       => 'body',
                'parameters' => $parameters,
            ],
        ];
    }
}

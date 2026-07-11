<?php

namespace App\Modules\Core\Support;

/**
 * Canonical list of toggleable hospital modules.
 * Used by the settings UI, the super-admin module control, the save endpoints,
 * and the `module:<key>` gating middleware. `category` groups them in the UI.
 */
class ModuleCatalog
{
    public const MODULES = [
        // AI & communication
        'ai_receptionist'      => ['name' => 'AI Receptionist', 'description' => 'Automated patient intake via WhatsApp', 'category' => 'AI & Communication'],
        'triage'               => ['name' => 'AI Triage', 'description' => 'Automatic urgency classification', 'category' => 'AI & Communication'],
        'doctor_assist'        => ['name' => 'Doctor Assist', 'description' => 'AI briefings and SOAP notes', 'category' => 'AI & Communication'],
        'whatsapp'             => ['name' => 'WhatsApp Integration', 'description' => 'Patient communication via WhatsApp', 'category' => 'AI & Communication'],
        'engagement'           => ['name' => 'Patient Engagement', 'description' => 'Reminders, follow-ups and feedback', 'category' => 'AI & Communication'],

        // Financial
        'billing'              => ['name' => 'Billing', 'description' => 'Bills, payments and the token counter', 'category' => 'Financial'],
        'billing_integrations' => ['name' => 'Billing Integrations', 'description' => 'Sync bills to external billing / ERP', 'category' => 'Financial'],
        'insurance'            => ['name' => 'Insurance', 'description' => 'Insurance verification and claims', 'category' => 'Financial'],

        // Clinical
        'vaccination'          => ['name' => 'Vaccination', 'description' => 'Vaccine records and due tracking', 'category' => 'Clinical'],
        'dietary'              => ['name' => 'Dietary', 'description' => 'Meal master and prescriptions', 'category' => 'Clinical'],
        'dental'               => ['name' => 'Dental', 'description' => 'Tooth charting and treatment plans', 'category' => 'Clinical'],
        'clinical_pathways'    => ['name' => 'Clinical Pathways', 'description' => 'Care-pathway templates and tracking', 'category' => 'Clinical'],
        'consent'              => ['name' => 'Consent Management', 'description' => 'Consent forms and compliance', 'category' => 'Clinical'],

        // Operations & quality
        'inventory'            => ['name' => 'Inventory', 'description' => 'Stock ledger and reorder alerts', 'category' => 'Operations & Quality'],
        'incidents'            => ['name' => 'Incident Reporting', 'description' => 'Safety incidents and CAPA', 'category' => 'Operations & Quality'],
        'housekeeping'         => ['name' => 'Housekeeping', 'description' => 'Facility issue and compliance tracking', 'category' => 'Operations & Quality'],
    ];

    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    /** Module keys added since the original catalog — used to back-fill existing hospitals. */
    public const NEW_MODULE_KEYS = [
        'vaccination', 'dietary', 'dental', 'clinical_pathways', 'consent',
        'inventory', 'incidents', 'housekeeping',
    ];

    /** @return array<string, array> grouped by category, preserving order. */
    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::MODULES as $key => $meta) {
            $grouped[$meta['category']][$key] = $meta;
        }

        return $grouped;
    }
}

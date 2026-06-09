<?php

return [
    'name' => env('MEDOS_APP_NAME', 'MedOS'),
    'version' => '2.5.54',

    'current_hospital_id' => null, // Set at runtime via middleware

    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'), // anthropic, openai
        'anthropic_key' => env('ANTHROPIC_API_KEY'),
        'anthropic_model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'openai_key' => env('OPENAI_API_KEY'),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4o'),
        'max_tokens' => env('AI_MAX_TOKENS', 2048),
        'temperature' => env('AI_TEMPERATURE', 0.3),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'meta'), // meta, gupshup
        'meta_token' => env('WHATSAPP_META_TOKEN'),
        'meta_phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'meta_business_id' => env('WHATSAPP_BUSINESS_ID'),
        'meta_verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
        'gupshup_api_key' => env('GUPSHUP_API_KEY'),
        'gupshup_app_name' => env('GUPSHUP_APP_NAME'),
    ],

    'triage' => [
        'emergency_threshold' => 0.9,
        'urgent_threshold' => 0.7,
        'semi_urgent_threshold' => 0.4,
        'ml_enabled' => env('TRIAGE_ML_ENABLED', false),
        'ml_endpoint' => env('TRIAGE_ML_ENDPOINT'),
    ],

    'queue' => [
        'urgency_weight' => 0.40,
        'wait_weight' => 0.25,
        'appointment_weight' => 0.20,
        'age_weight' => 0.10,
        'vip_weight' => 0.05,
        'starvation_increment_per_minute' => 0.01,
        'max_wait_escalation_minutes' => 45,
        'no_show_timeout_minutes' => 5,
        'evaluation_interval_seconds' => 60,
    ],

    'scheduling' => [
        'default_slot_duration' => 15,
        'buffer_slots_every_n_patients' => 5,
        'overbooking_enabled' => env('OVERBOOKING_ENABLED', false),
        'max_overbooking_percent' => 20,
        'advance_booking_days' => 30,
    ],

    'insurance' => [
        'auto_verify' => env('INSURANCE_AUTO_VERIFY', true),
        'pre_auth_auto_submit' => env('INSURANCE_PRE_AUTH_AUTO', false),
        'eligibility_cache_hours' => 24,
        'uae_dha_endpoint' => env('UAE_DHA_ENDPOINT'),
        'uae_dha_key' => env('UAE_DHA_API_KEY'),
    ],

    'languages' => [
        'supported' => ['en', 'hi', 'mr', 'kok', 'ar', 'ta', 'te', 'kn', 'bn'],
        'default' => 'en',
        'detection_confidence_threshold' => 0.8,
    ],

    'notifications' => [
        'appointment_reminder_hours' => [24, 2],
        'queue_update_enabled' => true,
        'feedback_delay_hours' => 2,
    ],

    'security' => [
        'phi_encryption_key' => env('PHI_ENCRYPTION_KEY'),
        'audit_retention_days' => 2555, // ~7 years
        'session_timeout_minutes' => 30,
        'max_login_attempts' => 5,
    ],
];

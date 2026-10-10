<?php

/*
|--------------------------------------------------------------------------
| Aisa platform configuration
|--------------------------------------------------------------------------
|
| Values that are still open decisions in the project document (section 17)
| are marked "OPEN DECISION". They have safe development defaults, and the
| launch check (php artisan aisa:launch-check) reports them as blockers
| until they are approved in the admin settings.
|
*/

return [

    'locales' => ['en', 'sw'],

    // Timestamps are stored in UTC and displayed in this timezone.
    'display_timezone' => env('AISA_DISPLAY_TIMEZONE', 'Africa/Dar_es_Salaam'),

    'ai' => [
        // "anthropic" or "mock". The mock provider is refused in production.
        'provider' => env('AISA_AI_PROVIDER', 'mock'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AISA_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('AISA_AI_EFFORT', 'medium'),
        'timeout' => (float) env('AISA_AI_TIMEOUT', 45),
        'max_retries' => (int) env('AISA_AI_MAX_RETRIES', 1),
        'refusal_fallback' => (bool) env('AISA_AI_REFUSAL_FALLBACK', true),
        'daily_request_cap' => (int) env('AISA_AI_DAILY_REQUEST_CAP', 2000),
        // Bump whenever a prompt in app/Services/Ai/Prompts changes, then rerun
        // the safety test set (php artisan test --filter=Safety).
        'prompt_version' => '2026-10-10.1',
        // Maximum gentle follow-up questions before offering guidance.
        'max_follow_up_questions' => 3,
        // How many recent messages are sent to the model per turn.
        'context_messages' => 30,
    ],

    // OPEN DECISION: name of the AI assistant. Must not be "Aisa".
    'assistant_name' => env('AISA_ASSISTANT_NAME', 'Msaidizi'),

    'tickets' => [
        'pepper' => env('AISA_TICKET_PEPPER'),
        // Failed code lookups allowed per IP before lockout.
        'max_attempts' => 5,
        'lockout_minutes' => 15,
        'remember_cookie' => 'aisa_device',
        'remember_days' => 30,
    ],

    // OPEN DECISION: response promise (24, 48 or 72 hours).
    'response_window_hours' => (int) env('AISA_RESPONSE_WINDOW_HOURS', 48),

    // OPEN DECISION: retention period.
    'retention_days' => (int) env('AISA_RETENTION_DAYS', 180),

    // OPEN DECISION: under-18 policy. "restrict" | "block" | "support".
    'minor_policy' => env('AISA_MINOR_POLICY', 'restrict'),

    'consent_version' => '2026-10-10',

    'safety' => [
        'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('AISA_SAFETY_ALERT_EMAILS', ''))))),
    ],

    'quick_exit' => [
        'url' => env('AISA_QUICK_EXIT_URL', 'https://www.google.com/search?q=weather'),
        'forget_device' => (bool) env('AISA_QUICK_EXIT_FORGETS_DEVICE', true),
    ],

    'admin' => [
        'idle_minutes' => (int) env('AISA_ADMIN_IDLE_MINUTES', 30),
        'max_login_attempts' => 5,
    ],

    'limits' => [
        'message_max_chars' => 4000,
        'messages_per_minute' => 8,
        'contact_per_hour' => 5,
    ],

    'categories' => ['relationship', 'family', 'work', 'grief', 'growth', 'other'],
    'urgency_levels' => ['low', 'medium', 'high', 'critical'],
];

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
        // "anthropic", "ollama" (a free model running locally) or "mock".
        // The mock provider is refused in production.
        'provider' => env('AISA_AI_PROVIDER', 'mock'),
        // Local models through Ollama (https://ollama.com). Free, private,
        // and slower on ordinary computers. See docs/ai-provider.md.
        'ollama' => [
            'url' => env('AISA_OLLAMA_URL', 'http://127.0.0.1:11434'),
            'model' => env('AISA_OLLAMA_MODEL', 'qwen2.5:3b'),
            'timeout' => (float) env('AISA_OLLAMA_TIMEOUT', 600),
            // Tokens of context; the prompt alone is several thousand.
            'context' => (int) env('AISA_OLLAMA_CONTEXT', 8192),
            // How long the model stays in memory after a request.
            'keep_alive' => env('AISA_OLLAMA_KEEP_ALIVE', '30m'),
        ],
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AISA_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('AISA_AI_EFFORT', 'medium'),
        'timeout' => (float) env('AISA_AI_TIMEOUT', 45),
        'max_retries' => (int) env('AISA_AI_MAX_RETRIES', 1),
        'refusal_fallback' => (bool) env('AISA_AI_REFUSAL_FALLBACK', true),
        'daily_request_cap' => (int) env('AISA_AI_DAILY_REQUEST_CAP', 2000),
        // Bump whenever a prompt in app/Services/Ai/Prompts changes, then rerun
        // the safety test set (php artisan test --filter=Safety).
        'prompt_version' => '2026-10-12.3',
        // Maximum gentle follow-up questions before offering guidance.
        'max_follow_up_questions' => 3,
        // How many recent messages are sent to the model per turn.
        'context_messages' => 30,
    ],

    // Guidance areas offered as entry points on the home page. The chosen
    // one is stored on the ticket and steers the AI; people can still talk
    // about anything.
    'modules' => ['wellbeing', 'parenting', 'money', 'career'],

    // Reference notes the AI draws on (resources/knowledge/<module>/*.md).
    // Each note must be checked by a qualified person before launch; set
    // AISA_KNOWLEDGE_REVIEWED=true once every note says "reviewed: true".
    'knowledge' => [
        'path' => resource_path('knowledge'),
        'max_notes' => (int) env('AISA_KNOWLEDGE_MAX_NOTES', 4),
        'reviewed' => (bool) env('AISA_KNOWLEDGE_REVIEWED', false),
    ],

    // Set AISA_MAMA_CARE_REVIEWED=true once a qualified health worker has
    // reviewed lang/*/mama.php. Until then the page says it is a draft.
    'mama_care_reviewed' => (bool) env('AISA_MAMA_CARE_REVIEWED', false),

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

    'categories' => [
        'relationship', 'family', 'parenting', 'womens_health', 'money', 'business',
        'career', 'education', 'work', 'grief', 'growth', 'other',
    ],
    'urgency_levels' => ['low', 'medium', 'high', 'critical'],
];

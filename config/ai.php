<?php

return [
    'enabled' => env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai'),
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 300),
    'timeout' => (int) env('AI_TIMEOUT', 30),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'organization' => env('OPENAI_ORGANIZATION'),
        // Empty uses https://api.openai.com/v1. Set only for a local fake.
        'base_url' => env('OPENAI_BASE_URL'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
];

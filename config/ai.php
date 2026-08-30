<?php

return [
    'ticket_hints' => [
        'enabled' => env('AI_TICKET_HINTS_ENABLED', false),
        'confidence_threshold' => (float) env('AI_TICKET_HINTS_CONFIDENCE_THRESHOLD', 0.85),
        'groq' => [
            'enabled' => env('AI_GROQ_ENABLED', true),
            'api_key' => env('GROQ_API_KEY'),
            'base_url' => env('AI_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('AI_GROQ_MODEL', 'openai/gpt-oss-20b'),
            'timeout_seconds' => (int) env('AI_GROQ_TIMEOUT_SECONDS', 30),
        ],
        'ollama' => [
            'base_url' => env('AI_OLLAMA_BASE_URL', 'http://ollama:11434'),
            'model' => env('AI_OLLAMA_MODEL', 'qwen3:4b'),
            'timeout_seconds' => (int) env('AI_OLLAMA_TIMEOUT_SECONDS', 90),
        ],
    ],
];

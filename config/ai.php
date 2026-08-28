<?php

return [
    'ticket_hints' => [
        'enabled' => env('AI_TICKET_HINTS_ENABLED', false),
        'confidence_threshold' => (float) env('AI_TICKET_HINTS_CONFIDENCE_THRESHOLD', 0.85),
        'ollama' => [
            'base_url' => env('AI_OLLAMA_BASE_URL', 'http://ollama:11434'),
            'model' => env('AI_OLLAMA_MODEL', 'qwen3:4b'),
            'timeout_seconds' => (int) env('AI_OLLAMA_TIMEOUT_SECONDS', 90),
        ],
    ],
];

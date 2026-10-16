<?php

return [
    'groq' => [
        'enabled' => env('AI_GROQ_ENABLED', true),
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('AI_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('AI_GROQ_MODEL', 'openai/gpt-oss-20b'),
        'timeout_seconds' => (int) env('AI_GROQ_TIMEOUT_SECONDS', 30),
    ],
    'gemini' => [
        'enabled' => env('AI_GEMINI_ENABLED', true),
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('AI_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('AI_GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'timeout_seconds' => (int) env('AI_GEMINI_TIMEOUT_SECONDS', 30),
    ],
    'ollama' => [
        'base_url' => env('AI_OLLAMA_BASE_URL', 'http://ollama:11434'),
        'model' => env('AI_OLLAMA_MODEL', 'qwen3:4b'),
        'timeout_seconds' => (int) env('AI_OLLAMA_TIMEOUT_SECONDS', 180),
    ],
    'ticket_hints' => [
        'enabled' => env('AI_TICKET_HINTS_ENABLED', false),
        'confidence_threshold' => (float) env('AI_TICKET_HINTS_CONFIDENCE_THRESHOLD', 0.85),
    ],
];

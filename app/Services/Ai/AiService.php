<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class AiService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @return array{content: string, provider: string, model: string}
     */
    public function generate(array $messages, array $schema, ?string $contextId = null): array
    {
        if ($this->groqIsConfigured()) {
            $response = Http::acceptJson()
                ->withToken(config('ai.groq.api_key'))
                ->timeout(config('ai.groq.timeout_seconds'))
                ->post(config('ai.groq.base_url').'/chat/completions', [
                    'model' => config('ai.groq.model'),
                    'messages' => $messages,
                    'temperature' => 0,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'structured_response',
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ]);

            if ($response->status() !== 429) {
                $response->throw();

                return [
                    'content' => $this->content($response->json('choices.0.message.content'), 'Groq'),
                    'provider' => 'groq',
                    'model' => config('ai.groq.model'),
                ];
            }

            Log::warning('Groq AI quota reached; using Gemini if is configured or Ollama as fallback.', [
                'context_id' => $contextId,
                'provider' => 'groq',
                'model' => config('ai.groq.model'),
                'http_status' => 429,
                'fallback_provider' => $this->geminiIsConfigured() ? 'gemini' : 'ollama',
            ]);
        }

        if ($this->geminiIsConfigured()) {
            $payload = [
                'contents' => $this->geminiContents($messages),
                'generationConfig' => [
                    'temperature' => 0,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                ],
            ];
            $systemInstruction = $this->geminiSystemInstruction($messages);

            if ($systemInstruction['parts'] !== []) {
                $payload['systemInstruction'] = $systemInstruction;
            }

            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => config('ai.gemini.api_key')])
                ->timeout(config('ai.gemini.timeout_seconds'))
                ->post(config('ai.gemini.base_url').'/models/'.config('ai.gemini.model').':generateContent', $payload);

            if ($response->status() !== 429) {
                $response->throw();

                return [
                    'content' => $this->content($response->json('candidates.0.content.parts.0.text'), 'Gemini'),
                    'provider' => 'gemini',
                    'model' => config('ai.gemini.model'),
                ];
            }

            Log::warning('Gemini AI quota reached; using Ollama fallback.', [
                'context_id' => $contextId,
                'provider' => 'gemini',
                'model' => config('ai.gemini.model'),
                'http_status' => 429,
                'fallback_provider' => 'ollama',
            ]);
        }

        $response = Http::acceptJson()
            ->timeout(config('ai.ollama.timeout_seconds'))
            ->post(config('ai.ollama.base_url').'/api/chat', [
                'model' => config('ai.ollama.model'),
                'stream' => false,
                'think' => false,
                'format' => $schema,
                'options' => ['temperature' => 0],
                'messages' => $messages,
            ])
            ->throw();

        return [
            'content' => $this->content($response->json('message.content'), 'Ollama'),
            'provider' => 'ollama',
            'model' => config('ai.ollama.model'),
        ];
    }

    public function configuredProvider(): string
    {
        if ($this->groqIsConfigured()) {
            return 'groq';
        }

        return $this->geminiIsConfigured() ? 'gemini' : 'ollama';
    }

    public function configuredModel(): string
    {
        if ($this->groqIsConfigured()) {
            return config('ai.groq.model');
        }

        return $this->geminiIsConfigured()
            ? config('ai.gemini.model')
            : config('ai.ollama.model');
    }

    private function groqIsConfigured(): bool
    {
        return config('ai.groq.enabled') && filled(config('ai.groq.api_key'));
    }

    private function geminiIsConfigured(): bool
    {
        return config('ai.gemini.enabled') && filled(config('ai.gemini.api_key'));
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    private function geminiSystemInstruction(array $messages): array
    {
        $instructions = array_filter($messages, fn (array $message): bool => $message['role'] === 'system');

        return [
            'parts' => array_map(fn (array $message): array => ['text' => $message['content']], $instructions),
        ];
    }

    /** @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function geminiContents(array $messages): array
    {
        $conversation = array_filter($messages, fn (array $message): bool => $message['role'] !== 'system');

        return array_map(fn (array $message): array => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $conversation);
    }

    private function content(mixed $content, string $provider): string
    {
        if (! is_string($content)) {
            throw new JsonException("A resposta da {$provider} não contém conteúdo válido.");
        }

        return $content;
    }
}

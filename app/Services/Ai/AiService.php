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
     * @return array{data: array<string, mixed>, provider: string, model: string}
     */
    public function generate(
        array $messages,
        array $schema,
        ?string $contextId = null,
    ): array
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

                return $this->result(
                    $response->json('choices.0.message.content'),
                    'Groq',
                    'groq',
                    config('ai.groq.model'),
                    $schema,
                );
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

                return $this->result(
                    $response->json('candidates.0.content.parts.0.text'),
                    'Gemini',
                    'gemini',
                    config('ai.gemini.model'),
                    $schema,
                );
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

        return $this->result(
            $response->json('message.content'),
            'Ollama',
            'ollama',
            config('ai.ollama.model'),
            $schema,
        );
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
            'parts' => array_values(array_map(
                fn (array $message): array => ['text' => $message['content']],
                $instructions,
            )),
        ];
    }

    /** @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function geminiContents(array $messages): array
    {
        $conversation = array_filter($messages, fn (array $message): bool => $message['role'] !== 'system');

        return array_values(array_map(
            fn (array $message): array => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ],
            $conversation,
        ));
    }

    /** @param array<string, mixed> $schema
     * @return array{data: array<string, mixed>, provider: string, model: string}
     */
    private function result(
        mixed $content,
        string $providerName,
        string $provider,
        string $model,
        array $schema,
    ): array
    {
        if (! is_string($content)) {
            throw new JsonException("A resposta da {$providerName} não contém conteúdo válido.");
        }

        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (! $this->matchesSchema($data, $schema)) {
            throw new JsonException("A resposta da {$providerName} não segue o schema esperado.");
        }

        return compact('data', 'provider', 'model');
    }

    /** @param array<string, mixed> $schema */
    private function matchesSchema(
        mixed $value,
        array $schema,
    ): bool
    {
        return $this->matchesValue($value, $schema);
    }

    /** @param array<string, mixed> $schema */
    private function matchesValue(
        mixed $value,
        array $schema,
    ): bool
    {
        $type = $schema['type'] ?? null;

        if (match ($type) {
            'object' => ! is_array($value) || array_is_list($value),
            'string' => ! is_string($value),
            'number' => ! is_int($value) && ! is_float($value),
            'integer' => ! is_int($value),
            'boolean' => ! is_bool($value),
            'array' => ! is_array($value) || ! array_is_list($value),
            default => true,
        }) {
            return false;
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                return false;
            }

            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                return false;
            }
        }

        if (is_string($value) && isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
            return false;
        }

        if ($type === 'object') {
            foreach ($schema['required'] ?? [] as $requiredKey) {
                if (! array_key_exists($requiredKey, $value)) {
                    return false;
                }
            }

            if (($schema['additionalProperties'] ?? true) === false
                && array_diff_key($value, $schema['properties'] ?? []) !== []) {
                return false;
            }

            foreach ($schema['properties'] ?? [] as $key => $propertySchema) {
                if (array_key_exists($key, $value) && ! $this->matchesValue($value[$key], $propertySchema)) {
                    return false;
                }
            }
        }

        if ($type === 'array' && isset($schema['items'])) {
            foreach ($value as $item) {
                if (! $this->matchesValue($item, $schema['items'])) {
                    return false;
                }
            }
        }

        return true;
    }
}

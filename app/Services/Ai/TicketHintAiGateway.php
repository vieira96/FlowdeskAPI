<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class TicketHintAiGateway
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @return array{content: string, model: string}
     */
    public function generate(array $messages, array $schema, ?string $ticketId = null): array
    {
        if ($this->groqIsConfigured()) {
            $response = Http::acceptJson()
                ->withToken(config('ai.ticket_hints.groq.api_key'))
                ->timeout(config('ai.ticket_hints.groq.timeout_seconds'))
                ->post(config('ai.ticket_hints.groq.base_url').'/chat/completions', [
                    'model' => config('ai.ticket_hints.groq.model'),
                    'messages' => $messages,
                    'temperature' => 0,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'ticket_hint',
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ]);

            if ($response->status() !== 429) {
                $response->throw();

                return [
                    'content' => $this->groqContent($response->json('choices.0.message.content')),
                    'model' => config('ai.ticket_hints.groq.model'),
                ];
            }

            Log::warning('Groq AI quota reached; using Ollama fallback.', [
                'ticket_id' => $ticketId,
                'provider' => 'groq',
                'model' => config('ai.ticket_hints.groq.model'),
                'http_status' => 429,
                'fallback_provider' => 'ollama',
            ]);
        }

        $response = Http::acceptJson()
            ->timeout(config('ai.ticket_hints.ollama.timeout_seconds'))
            ->post(config('ai.ticket_hints.ollama.base_url').'/api/chat', [
                'model' => config('ai.ticket_hints.ollama.model'),
                'stream' => false,
                'think' => false,
                'format' => $schema,
                'options' => ['temperature' => 0],
                'messages' => $messages,
            ])
            ->throw();

        return [
            'content' => $this->ollamaContent($response->json('message.content')),
            'model' => config('ai.ticket_hints.ollama.model'),
        ];
    }

    private function groqIsConfigured(): bool
    {
        return config('ai.ticket_hints.groq.enabled')
            && filled(config('ai.ticket_hints.groq.api_key'));
    }

    private function groqContent(mixed $content): string
    {
        if (! is_string($content)) {
            throw new JsonException('A resposta da Groq não contém conteúdo válido.');
        }

        return $content;
    }

    private function ollamaContent(mixed $content): string
    {
        if (! is_string($content)) {
            throw new JsonException('A resposta do Ollama não contém conteúdo válido.');
        }

        return $content;
    }
}

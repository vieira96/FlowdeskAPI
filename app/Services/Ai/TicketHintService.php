<?php

namespace App\Services\Ai;

use App\Models\Ai\TicketAiSuggestion;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Services\Notification\TicketNotificationService;
use JsonException;

class TicketHintService
{
    private const array SENSITIVE_TERMS = [
        'senha',
        'password',
        'credencial',
        'token',
        'vazamento',
        'segurança',
        'seguranca',
        'malware',
        'ransomware',
    ];

    public function __construct(
        private readonly TicketNotificationService $ticketNotificationService,
        private readonly TicketHintAiGateway $ticketHintAiGateway,
    ) {}

    public function generateFor(Ticket $ticket): void
    {
        $suggestion = TicketAiSuggestion::query()->firstOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'status' => 'pending',
                'model' => $this->configuredModel(),
            ],
        );

        if ($suggestion->status !== 'pending') {
            return;
        }

        if ($this->containsSensitiveContent($ticket)) {
            $suggestion->update([
                'status' => 'skipped',
                'classification' => 'unsafe',
                'confidence' => 1,
                'failure_reason' => 'Conteúdo sensível exige análise humana.',
                'generated_at' => now(),
            ]);

            return;
        }

        try {
            $result = $this->askAi($ticket);
            $analysis = $result['analysis'];
        } catch (\Throwable $exception) {
            $suggestion->update([
                'status' => 'failed',
                'failure_reason' => 'Não foi possível gerar a sugestão automática.',
                'generated_at' => now(),
            ]);

            report($exception);

            return;
        }

        $suggestion->update([
            'status' => $this->shouldPublish($analysis) ? 'published' : 'skipped',
            'classification' => $analysis['classification'],
            'confidence' => $analysis['confidence'],
            'suggestion' => $analysis['suggestion'] ?: null,
            'model' => $result['model'],
            'generated_at' => now(),
        ]);

        if (! $this->shouldPublish($analysis)) {
            return;
        }

        TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'source' => 'ai',
            'metadata' => [
                'model' => $result['model'],
                'confidence' => $analysis['confidence'],
            ],
            'body' => "Dica automática do Assistente IA:\n\n{$analysis['suggestion']}",
        ]);

        $ticket->loadMissing('requester');
        $this->ticketNotificationService->notifyAiHintPublished($ticket);
    }

    /** @return array{analysis: array{classification: string, confidence: float, suggestion: string}, model: string} */
    private function askAi(Ticket $ticket): array
    {
        $result = $this->ticketHintAiGateway->generate([
                    [
                        'role' => 'system',
                        'content' => <<<'PROMPT'
Você faz a triagem de chamados internos em português do Brasil. Classifique como simple, complex ou unsafe.

Use simple somente quando uma orientação segura, sem risco e sem ação administrativa puder ajudar uma pessoa leiga. Nunca peça senha, token, dados pessoais, alteração de configurações de segurança, acesso privilegiado ou instalação de programas. Para complex e unsafe, suggestion deve ser uma string vazia.

Quando a classificação for simple, escreva a suggestion para uma pessoa sem conhecimento técnico: seja acolhedor, claro e objetivo. Não use jargão e não responda apenas com uma ordem como "verifique o papel". Use este formato, em texto simples:

"Olá! Vamos tentar resolver isso juntos.\n\n1. [primeiro passo simples]\n2. [segundo passo simples, se necessário]\n\nSe não funcionar, responda a este chamado contando o que aconteceu. A equipe vai continuar te ajudando."

Inclua no máximo três passos, cada um com uma ação que a pessoa pode executar com segurança. Não invente informações e não afirme que o problema foi resolvido.
PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => "Título: {$ticket->title}\n\nDescrição: {$ticket->description}",
                    ],
        ], $this->responseSchema());
        $content = $result['content'];

        /** @var array{classification?: mixed, confidence?: mixed, suggestion?: mixed} $analysis */
        $analysis = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $classification = $analysis['classification'] ?? null;
        $confidence = $analysis['confidence'] ?? null;
        $suggestion = $analysis['suggestion'] ?? null;

        if (! in_array($classification, ['simple', 'complex', 'unsafe'], true)
            || ! is_numeric($confidence)
            || (float) $confidence < 0
            || (float) $confidence > 1
            || ! is_string($suggestion)) {
            throw new JsonException('A resposta da IA não segue o formato esperado.');
        }

        return [
            'analysis' => [
                'classification' => $classification,
                'confidence' => (float) $confidence,
                'suggestion' => trim(mb_substr($suggestion, 0, 1000)),
            ],
            'model' => $result['model'],
        ];
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'classification' => ['type' => 'string', 'enum' => ['simple', 'complex', 'unsafe']],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'suggestion' => ['type' => 'string', 'maxLength' => 1000],
            ],
            'required' => ['classification', 'confidence', 'suggestion'],
            'additionalProperties' => false,
        ];
    }

    /** @param array{classification: string, confidence: float, suggestion: string} $analysis */
    private function shouldPublish(array $analysis): bool
    {
        return $analysis['classification'] === 'simple'
            && $analysis['confidence'] >= config('ai.ticket_hints.confidence_threshold')
            && $analysis['suggestion'] !== '';
    }

    private function containsSensitiveContent(Ticket $ticket): bool
    {
        $content = mb_strtolower("{$ticket->title} {$ticket->description}");

        foreach (self::SENSITIVE_TERMS as $term) {
            if (str_contains($content, $term)) {
                return true;
            }
        }

        return false;
    }

    private function configuredModel(): string
    {
        return config('ai.ticket_hints.groq.enabled') && filled(config('ai.ticket_hints.groq.api_key'))
            ? config('ai.ticket_hints.groq.model')
            : config('ai.ticket_hints.ollama.model');
    }
}

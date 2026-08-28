<?php

namespace Tests\Feature\Ai;

use App\Jobs\Ai\GenerateTicketHintJob;
use App\Models\Ai\TicketAiSuggestion;
use App\Models\Team\TeamCategory;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Services\Ai\TicketHintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketHintTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_queues_ai_triage_when_enabled(): void
    {
        Bus::fake();
        config()->set('ai.ticket_hints.enabled', true);
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();

        $response = $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/tickets', [
                'title' => 'Impressora sem papel',
                'description' => 'A impressora informa que está sem papel.',
                'category_id' => $category->id,
            ])
            ->assertCreated();

        Bus::assertDispatched(GenerateTicketHintJob::class, fn (GenerateTicketHintJob $job) => $job->ticketId === $response->json('data.id'));
    }

    public function test_ai_creates_a_labeled_comment_only_for_a_high_confidence_simple_ticket(): void
    {
        config()->set('ai.ticket_hints.enabled', true);
        Http::fake([
            'http://ollama:11434/api/chat' => Http::response([
                'message' => [
                    'content' => json_encode([
                        'classification' => 'simple',
                        'confidence' => 0.92,
                        'suggestion' => 'Verifique se há papel na bandeja e se a impressora está online.',
                    ], JSON_THROW_ON_ERROR),
                ],
            ]),
        ]);

        $ticket = $this->createTicket('Impressora sem papel', 'A impressora informa que está sem papel.');

        app(TicketHintService::class)->generateFor($ticket);

        Http::assertSent(function (Request $request): bool {
            $prompt = $request['messages'][0]['content'] ?? '';

            return str_contains($prompt, 'pessoa sem conhecimento técnico')
                && str_contains($prompt, 'Olá! Vamos tentar resolver isso juntos.');
        });

        $this->assertDatabaseHas('ticket_ai_suggestions', [
            'ticket_id' => $ticket->id,
            'status' => 'published',
            'classification' => 'simple',
        ]);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'source' => 'ai',
            'user_id' => null,
        ]);

        $suggestion = TicketAiSuggestion::query()->where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame(0.92, $suggestion->confidence);
        $this->assertSame('Verifique se há papel na bandeja e se a impressora está online.', $suggestion->suggestion);

        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.ai.status', 'published')
            ->assertJsonPath('data.ai.classification', 'simple')
            ->assertJsonPath('data.ai.confidence', 0.92)
            ->assertJsonPath('data.ai.suggestion', 'Verifique se há papel na bandeja e se a impressora está online.')
            ->assertJsonMissingPath('data.ai.model')
            ->assertJsonPath('data.comments.0.source', 'ai')
            ->assertJsonPath('data.comments.0.author.name', 'Assistente IA');
    }

    public function test_sensitive_ticket_is_not_sent_to_the_ai_or_published_as_a_hint(): void
    {
        Http::fake();
        $ticket = $this->createTicket('Preciso redefinir minha senha', 'Não consigo acessar o sistema.');

        app(TicketHintService::class)->generateFor($ticket);

        Http::assertNothingSent();
        $this->assertDatabaseHas('ticket_ai_suggestions', [
            'ticket_id' => $ticket->id,
            'status' => 'skipped',
            'classification' => 'unsafe',
        ]);
        $this->assertDatabaseMissing('ticket_comments', [
            'ticket_id' => $ticket->id,
            'source' => 'ai',
        ]);
    }

    private function createTicket(string $title, string $description): Ticket
    {
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();

        return Ticket::query()->create([
            'title' => $title,
            'description' => $description,
            'category_id' => $category->id,
            'team_id' => $category->team_id,
            'requester_id' => $requester->id,
        ]);
    }
}

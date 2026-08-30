<?php

namespace Tests\Feature\Ai;

use App\Jobs\Ai\GenerateTicketHintJob;
use App\Models\Ai\TicketAiSuggestion;
use App\Models\Team\TeamCategory;
use App\Models\Team\TeamMember;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Notifications\Ticket\TicketActivityNotification;
use App\Services\Ai\TicketHintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TicketHintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.ticket_hints.groq.api_key', null);
    }

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
        Event::fake([BroadcastNotificationCreated::class]);
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
        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $ticket->requester_id,
            'type' => TicketActivityNotification::class,
        ]);
        $this->assertSame(
            'ticket.ai_hint_published',
            $ticket->requester->notifications()->latest()->firstOrFail()->data['event'],
        );
        Event::assertDispatched(BroadcastNotificationCreated::class);

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
            ->assertJsonCount(0, 'data.comments');
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
        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $ticket->requester_id,
            'type' => TicketActivityNotification::class,
        ]);
    }

    public function test_team_agents_are_notified_when_ai_does_not_publish_a_hint(): void
    {
        config()->set('ai.ticket_hints.enabled', true);
        Http::fake([
            'http://ollama:11434/api/chat' => Http::response([
                'message' => ['content' => json_encode([
                    'classification' => 'complex',
                    'confidence' => 0.95,
                    'suggestion' => '',
                ], JSON_THROW_ON_ERROR)],
            ]),
        ]);
        $ticket = $this->createTicket('Servidor indisponível', 'O sistema inteiro ficou indisponível para todos os usuários.');
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        TeamMember::query()->create(['team_id' => $ticket->team_id, 'user_id' => $agent->id]);

        app(TicketHintService::class)->generateFor($ticket);

        $notification = $agent->notifications()->latest()->firstOrFail();
        $this->assertSame('ticket.created_without_ai_hint', $notification->data['event']);
        $this->assertSame($ticket->id, $notification->data['ticket']['id']);
    }

    public function test_it_uses_groq_when_an_api_key_is_configured(): void
    {
        config()->set('ai.ticket_hints.groq.api_key', 'groq-test-key');
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'classification' => 'simple',
                    'confidence' => 0.91,
                    'suggestion' => 'Verifique o cabo da impressora.',
                ], JSON_THROW_ON_ERROR)]]],
            ]),
        ]);
        $ticket = $this->createTicket('Impressora sem conexão', 'A impressora não está disponível.');

        app(TicketHintService::class)->generateFor($ticket);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer groq-test-key'));
        $this->assertDatabaseHas('ticket_ai_suggestions', [
            'ticket_id' => $ticket->id,
            'status' => 'published',
            'model' => 'openai/gpt-oss-20b',
        ]);
    }

    public function test_it_falls_back_to_ollama_only_when_groq_rate_limit_is_reached(): void
    {
        config()->set('ai.ticket_hints.groq.api_key', 'groq-test-key');
        Log::spy();
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([], 429),
            'http://ollama:11434/api/chat' => Http::response([
                'message' => ['content' => json_encode([
                    'classification' => 'simple',
                    'confidence' => 0.9,
                    'suggestion' => 'Recoloque o papel na bandeja.',
                ], JSON_THROW_ON_ERROR)],
            ]),
        ]);
        $ticket = $this->createTicket('Impressora sem papel', 'A impressora informa que está sem papel.');

        app(TicketHintService::class)->generateFor($ticket);

        Http::assertSentCount(2);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Groq AI quota reached; using Ollama fallback.'
                && $context['ticket_id'] === $ticket->id
                && $context['http_status'] === 429
                && $context['fallback_provider'] === 'ollama');
        $this->assertDatabaseHas('ticket_ai_suggestions', [
            'ticket_id' => $ticket->id,
            'status' => 'published',
            'model' => 'qwen3:4b',
        ]);
    }

    public function test_it_logs_ai_generation_failures_without_ticket_content(): void
    {
        Log::spy();
        Http::fake(['http://ollama:11434/api/chat' => Http::response([], 500)]);
        $ticket = $this->createTicket('Impressora sem papel', 'Descrição que não deve ser registrada no log.');

        app(TicketHintService::class)->generateFor($ticket);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Ticket AI hint generation failed.'
                && $context['ticket_id'] === $ticket->id
                && $context['provider'] === 'ollama'
                && ! array_key_exists('description', $context));
        $this->assertDatabaseHas('ticket_ai_suggestions', [
            'ticket_id' => $ticket->id,
            'status' => 'failed',
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

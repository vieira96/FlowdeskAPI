<?php

namespace Tests\Feature\Api\Ticket;

use App\Models\Ai\TicketAiSuggestion;
use App\Models\Team\TeamCategory;
use App\Models\Team\TeamMember;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanAssistanceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_request_human_assistance_after_an_ai_hint(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();
        TeamMember::query()->create(['team_id' => $category->team_id, 'user_id' => $agent->id]);
        $ticket = Ticket::query()->create([
            'title' => 'Impressora sem papel',
            'description' => 'A impressora não imprime porque está sem papel.',
            'category_id' => $category->id,
            'team_id' => $category->team_id,
            'requester_id' => $requester->id,
        ]);
        TicketAiSuggestion::query()->create([
            'ticket_id' => $ticket->id,
            'status' => 'published',
            'classification' => 'simple',
            'confidence' => 0.9,
            'suggestion' => 'Coloque papel na bandeja.',
            'model' => 'test',
            'generated_at' => now(),
        ]);

        $requestedAt = CarbonImmutable::parse('2026-08-30 10:00:00', 'UTC');
        $this->travelTo($requestedAt);

        try {
            $response = $this->withToken($requester->createToken('test')->plainTextToken)
                ->postJson("/api/v1/tickets/{$ticket->id}/request-human-assistance")
                ->assertOk()
                ->assertJsonPath('data.id', $ticket->id);

            $this->assertSame($requestedAt->toISOString(), $response->json('data.human_assistance_requested_at'));
            $this->assertSame($requestedAt->addHours(4)->toISOString(), $response->json('data.sla.first_response_due_at'));
            $this->assertSame($requestedAt->addDay()->toISOString(), $response->json('data.sla.resolution_due_at'));

            $notification = $agent->notifications()->latest()->firstOrFail();
            $this->assertSame('ticket.human_assistance_requested', $notification->data['event']);
            $this->assertSame($ticket->id, $notification->data['ticket']['id']);

            $this->withToken($requester->createToken('test')->plainTextToken)
                ->postJson("/api/v1/tickets/{$ticket->id}/request-human-assistance")
                ->assertOk();

            $this->assertSame(1, $agent->notifications()->count());
        } finally {
            $this->travelBack();
        }
    }
}

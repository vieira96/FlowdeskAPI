<?php

namespace Tests\Feature\Api\Ticket;

use App\Models\Team\Team;
use App\Models\Team\TeamCategory;
use App\Models\Team\TeamMember;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_ticket_is_assigned_to_the_team_of_its_selected_category(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/tickets', [
                'title' => 'Impressora não imprime',
                'description' => 'A impressora do Financeiro está parada.',
                'category_id' => $category->id,
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.team.name', 'Suporte de TI')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.assignee_id', null);

        $this->assertDatabaseHas('tickets', [
            'category_id' => $category->id,
            'team_id' => $category->team_id,
            'requester_id' => $requester->id,
        ]);
    }

    public function test_sla_deadlines_are_calculated_from_ticket_priority(): void
    {
        config()->set('ai.ticket_hints.enabled', false);
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();
        $startedAt = CarbonImmutable::parse('2026-08-28 10:00:00', 'UTC');

        $this->travelTo($startedAt);

        try {
            $response = $this->withToken($requester->createToken('test')->plainTextToken)
                ->postJson('/api/v1/tickets', [
                    'title' => 'Equipamento indisponível',
                    'description' => 'Chamado de prioridade alta para validar o SLA.',
                    'category_id' => $category->id,
                    'priority' => 'high',
                ])
                ->assertCreated();

            $ticket = Ticket::query()->findOrFail($response->json('data.id'));

            $this->assertTrue($ticket->first_response_due_at->equalTo($startedAt->addHour()));
            $this->assertTrue($ticket->resolution_due_at->equalTo($startedAt->addHours(8)));
            $this->assertNull($ticket->first_responded_at);
            $this->assertNull($ticket->resolved_at);
        } finally {
            $this->travelBack();
        }
    }

    public function test_sla_is_not_started_while_ticket_is_awaiting_ai_triage(): void
    {
        config()->set('ai.ticket_hints.enabled', true);
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();

        $response = $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/tickets', [
                'title' => 'Impressora sem papel',
                'description' => 'A impressora informa que está sem papel.',
                'category_id' => $category->id,
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sla.first_response_due_at', null)
            ->assertJsonPath('data.sla.resolution_due_at', null);

        $ticket = Ticket::query()->findOrFail($response->json('data.id'));
        $this->assertNull($ticket->first_response_due_at);
        $this->assertNull($ticket->resolution_due_at);
    }

    public function test_the_payload_cannot_override_the_team_derived_from_the_category(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Rede')->firstOrFail();
        $wrongTeam = Team::query()->where('name', 'Suporte de TI')->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/tickets', [
                'title' => 'Rede indisponível',
                'description' => 'Não consigo acessar a internet.',
                'category_id' => $category->id,
                'team_id' => $wrongTeam->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.team.name', 'Infraestrutura');
    }

    public function test_an_inactive_category_cannot_be_used_to_create_a_ticket(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->create([
            'team_id' => Team::query()->firstOrFail()->id,
            'name' => 'Categoria desativada',
            'is_active' => false,
        ]);

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/tickets', [
                'title' => 'Chamado inválido',
                'description' => 'Esta categoria não deve aceitar chamados.',
                'category_id' => $category->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_an_agent_cannot_create_a_ticket(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();

        $this->withToken($agent->createToken('test')->plainTextToken)
            ->postJson('/api/v1/tickets', [
                'title' => 'Tentativa de chamado',
                'description' => 'Agentes não abrem chamados neste fluxo.',
                'category_id' => $category->id,
            ])
            ->assertForbidden();
    }

    public function test_an_agent_only_lists_tickets_from_their_teams(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $supportTeam = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        $supportCategory = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();
        $infrastructureCategory = TeamCategory::query()->where('name', 'Rede')->firstOrFail();
        TeamMember::query()->create(['team_id' => $supportTeam->id, 'user_id' => $agent->id]);

        $supportTicket = $this->createTicket($supportCategory, $requester);
        $this->createTicket($infrastructureCategory, $requester);

        $this->withToken($agent->createToken('test')->plainTextToken)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $supportTicket->id);
    }

    public function test_an_administrator_can_list_all_tickets(): void
    {
        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);
        $this->createTicket(TeamCategory::query()->where('name', 'Rede')->firstOrFail(), $requester);

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_requester_only_lists_their_own_tickets(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $otherRequester = User::factory()->create();
        $ownTicket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);
        $this->createTicket(TeamCategory::query()->where('name', 'Rede')->firstOrFail(), $otherRequester);

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownTicket->id)
            ->assertJsonPath('data.0.requester_id', $requester->id);
    }

    public function test_a_requester_can_view_their_own_ticket(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id);
    }

    public function test_a_requester_cannot_view_another_requesters_ticket(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $otherRequester = User::factory()->create();
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $otherRequester);

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    public function test_an_agent_cannot_view_a_ticket_from_another_team(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $supportTeam = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        TeamMember::query()->create(['team_id' => $supportTeam->id, 'user_id' => $agent->id]);
        $infrastructureTicket = $this->createTicket(
            TeamCategory::query()->where('name', 'Rede')->firstOrFail(),
            $requester,
        );

        $this->withToken($agent->createToken('test')->plainTextToken)
            ->getJson("/api/v1/tickets/{$infrastructureTicket->id}")
            ->assertForbidden();
    }

    public function test_an_agent_from_the_ticket_team_can_assume_resolve_and_close_a_ticket(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);
        $token = $agent->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/tickets/{$ticket->id}/assume")
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $agent->id)
            ->assertJsonPath('data.assignee.name', $agent->name)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'agent_id' => $agent->id,
            'team_id' => $team->id,
            'source' => 'manual',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->withToken($token)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_assuming_and_resolving_a_ticket_records_sla_milestones(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);
        $token = $agent->createToken('test')->plainTextToken;
        $firstResponseAt = CarbonImmutable::parse('2026-08-28 10:00:00', 'UTC');
        $resolvedAt = $firstResponseAt->addHours(2);

        $this->travelTo($firstResponseAt);

        try {
            $this->withToken($token)
                ->postJson("/api/v1/tickets/{$ticket->id}/assume")
                ->assertOk()
                ->assertJsonPath('data.sla.first_responded_at', $firstResponseAt->toISOString());

            $this->travelTo($resolvedAt);

            $this->withToken($token)
                ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
                ->assertOk()
                ->assertJsonPath('data.sla.resolved_at', $resolvedAt->toISOString());

            $ticket->refresh();
            $this->assertTrue($ticket->first_responded_at->equalTo($firstResponseAt));
            $this->assertTrue($ticket->resolved_at->equalTo($resolvedAt));
        } finally {
            $this->travelBack();
        }
    }

    public function test_only_the_assigned_agent_can_comment_on_a_ticket(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);
        $token = $agent->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->id}/assume")->assertOk();

        $this->withToken($token)
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Estou verificando o equipamento.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Estou verificando o equipamento.')
            ->assertJsonPath('data.author.id', $agent->id);
    }

    public function test_requester_can_comment_on_own_ticket(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Ainda preciso de ajuda com a impressora.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Ainda preciso de ajuda com a impressora.')
            ->assertJsonPath('data.source', 'requester')
            ->assertJsonPath('data.author.id', $requester->id);
    }

    public function test_requester_cannot_comment_on_another_requesters_ticket(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $otherRequester = User::factory()->create(['role_id' => $requester->role_id]);
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $otherRequester);

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Tentativa indevida.'])
            ->assertForbidden();
    }

    public function test_an_agent_cannot_skip_the_status_transition_flow(): void
    {
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $ticket = $this->createTicket(TeamCategory::query()->where('name', 'Impressora')->firstOrFail(), $requester);
        $token = $agent->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->id}/assume")->assertOk();

        $this->withToken($token)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function createTicket(TeamCategory $category, User $requester): Ticket
    {
        return Ticket::query()->create([
            'title' => "Ticket {$category->name}",
            'description' => 'Ticket criado para teste.',
            'category_id' => $category->id,
            'team_id' => $category->team_id,
            'requester_id' => $requester->id,
        ]);
    }
}

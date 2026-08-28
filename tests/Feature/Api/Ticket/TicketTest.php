<?php

namespace Tests\Feature\Api\Ticket;

use App\Models\Team\Team;
use App\Models\Team\TeamCategory;
use App\Models\Team\TeamMember;
use App\Models\Ticket\Ticket;
use App\Models\User;
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

    public function test_a_requester_cannot_list_tickets(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->getJson('/api/v1/tickets')
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
            ->assertJsonPath('data.status', 'in_progress');

        $this->withToken($token)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->withToken($token)
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
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

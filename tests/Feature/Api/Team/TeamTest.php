<?php

namespace Tests\Feature\Api\Team;

use App\Models\Access\Role;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_create_a_team_with_agents(): void
    {
        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'agent')->value('id'),
        ]);

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/teams', [
                'name' => 'Atendimento Interno',
                'description' => 'Atendimento de tecnologia.',
                'agent_ids' => [$agent->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Atendimento Interno')
            ->assertJsonPath('data.agents.0.id', $agent->id)
            ->assertJsonPath('data.agents.0.role', 'agent');

        $this->assertDatabaseHas('team_members', ['user_id' => $agent->id]);
    }

    public function test_an_administrator_can_attach_an_agent_to_an_existing_team(): void
    {
        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'agent')->value('id'),
        ]);
        $team = Team::query()->create(['name' => 'Infraestrutura', 'created_by' => $administrator->id]);

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->postJson("/api/v1/teams/{$team->id}/agents", ['agent_ids' => [$agent->id]])
            ->assertOk()
            ->assertJsonPath('data.agents.0.id', $agent->id);
    }

    public function test_only_an_administrator_can_manage_teams(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/teams', ['name' => 'Suporte de TI'])
            ->assertForbidden();
    }

    public function test_an_administrator_can_list_teams(): void
    {
        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->getJson('/api/v1/teams')
            ->assertOk();
    }

    public function test_a_requester_cannot_list_teams(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->getJson('/api/v1/teams')
            ->assertForbidden();
    }

    public function test_a_requester_cannot_be_attached_as_an_agent(): void
    {
        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->create(['name' => 'Suporte de TI', 'created_by' => $administrator->id]);

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->postJson("/api/v1/teams/{$team->id}/agents", ['agent_ids' => [$requester->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agent_ids');
    }
}

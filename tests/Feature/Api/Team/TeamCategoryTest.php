<?php

namespace Tests\Feature\Api\Team;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_list_active_categories_with_their_teams(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->getJson('/api/v1/teams/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Acesso a sistemas')
            ->assertJsonPath('data.0.team.name', 'Suporte de TI');
    }

    public function test_an_administrator_can_create_a_category_for_a_team(): void
    {
        $administrator = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();

        $this->withToken($administrator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/teams/categories', [
                'team_id' => $team->id,
                'name' => 'VPN',
                'description' => 'Acesso remoto à rede corporativa.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'VPN')
            ->assertJsonPath('data.team.id', $team->id);
    }

    public function test_a_requester_cannot_create_a_category(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->firstOrFail();

        $this->withToken($requester->createToken('test')->plainTextToken)
            ->postJson('/api/v1/teams/categories', [
                'team_id' => $team->id,
                'name' => 'VPN',
            ])
            ->assertForbidden();
    }
}

<?php

namespace App\Services\Team;

use App\Models\Team\Team;
use App\Models\Team\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function create(array $data, User $administrator): Team
    {
        return DB::transaction(function () use ($data, $administrator): Team {
            $team = Team::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $administrator->id,
            ]);

            $this->attachAgents($team, $data['agent_ids'] ?? []);

            return $team->load('agents.role');
        });
    }

    public function attachAgents(Team $team, array $agentIds): Team
    {
        $agentIds = array_values(array_unique($agentIds));

        if ($agentIds === []) {
            return $team->load('agents.role');
        }

        $agents = User::query()
            ->whereIn('id', $agentIds)
            ->whereHas('role', fn ($query) => $query->where('slug', 'agent'))
            ->get(['id']);

        if ($agents->count() !== count($agentIds)) {
            throw ValidationException::withMessages([
                'agent_ids' => 'Todos os usuários vinculados à equipe devem possuir a role agent.',
            ]);
        }

        $now = now();
        TeamMember::query()->insertOrIgnore(
            $agents->map(fn (User $agent) => [
                'id' => (string) Str::uuid(),
                'team_id' => $team->id,
                'user_id' => $agent->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );

        return $team->load('agents.role');
    }
}

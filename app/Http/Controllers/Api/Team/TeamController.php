<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Team\AttachAgentsRequest;
use App\Http\Requests\Api\Team\CreateTeamRequest;
use App\Http\Resources\Api\Team\TeamResource;
use App\Models\Team\Team;
use App\Services\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teamService) {}

    public function index(): AnonymousResourceCollection
    {
        return TeamResource::collection(
            Team::query()->with('agents.role')->orderBy('name')->paginate(),
        );
    }

    public function store(CreateTeamRequest $request): JsonResponse
    {
        $team = $this->teamService->create($request->validated(), $request->user());

        return (new TeamResource($team))->response()->setStatusCode(201);
    }

    public function attachAgents(AttachAgentsRequest $request, Team $team): TeamResource
    {
        return new TeamResource($this->teamService->attachAgents($team, $request->validated('agent_ids')));
    }
}

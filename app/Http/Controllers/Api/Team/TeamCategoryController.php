<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Team\CreateTeamCategoryRequest;
use App\Http\Resources\Api\Team\TeamCategoryResource;
use App\Services\Team\TeamCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamCategoryController extends Controller
{
    public function __construct(private readonly TeamCategoryService $teamCategoryService) {}

    public function index(): AnonymousResourceCollection
    {
        return TeamCategoryResource::collection($this->teamCategoryService->paginate());
    }

    public function store(CreateTeamCategoryRequest $request): JsonResponse
    {
        $category = $this->teamCategoryService->create($request->validated());

        return (new TeamCategoryResource($category))->response()->setStatusCode(201);
    }
}

<?php

namespace App\Http\Resources\Api\Team;

use App\Http\Resources\Api\Auth\UserResource;
use App\Models\Team\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'agents' => UserResource::collection($this->whenLoaded('agents')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

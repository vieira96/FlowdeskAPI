<?php

namespace App\Http\Resources\Api\Ticket;

use App\Models\Ticket\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'sla' => [
                'first_response_due_at' => $this->first_response_due_at?->toISOString(),
                'first_responded_at' => $this->first_responded_at?->toISOString(),
                'resolution_due_at' => $this->resolution_due_at?->toISOString(),
                'resolved_at' => $this->resolved_at?->toISOString(),
            ],
            'requester_id' => $this->requester_id,
            'assignee_id' => $this->assignee_id,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ]),
            'ai' => $this->whenLoaded('aiSuggestion', fn () => $this->aiSuggestion === null ? null : [
                'status' => $this->aiSuggestion->status,
                'classification' => $this->aiSuggestion->classification,
                'confidence' => $this->aiSuggestion->confidence,
                'suggestion' => $this->aiSuggestion->suggestion,
                'generated_at' => $this->aiSuggestion->generated_at?->toISOString(),
            ]),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

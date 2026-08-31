<?php

namespace App\Http\Resources\Api\Ticket;

use App\Models\Ticket\TicketStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketStatusHistory */
class TicketStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'actor' => $this->actor_id === null
                ? ['type' => 'system', 'name' => 'Sistema']
                : $this->whenLoaded('actor', fn () => [
                    'id' => $this->actor->id,
                    'name' => $this->actor->name,
                ]),
            'changed_at' => $this->changed_at?->toISOString(),
        ];
    }
}

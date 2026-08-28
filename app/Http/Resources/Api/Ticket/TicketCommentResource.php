<?php

namespace App\Http\Resources\Api\Ticket;

use App\Models\Ticket\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketComment */
class TicketCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'source' => $this->source,
            'author' => $this->source === 'ai'
                ? ['type' => 'ai', 'name' => 'Assistente IA']
                : $this->whenLoaded('author', fn () => [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                ]),
            'ai' => $this->when($this->source === 'ai', fn () => [
                'confidence' => $this->metadata['confidence'] ?? null,
                'model' => $this->metadata['model'] ?? null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

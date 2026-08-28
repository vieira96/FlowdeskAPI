<?php

namespace App\Models\Ai;

use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'status',
    'classification',
    'confidence',
    'suggestion',
    'model',
    'failure_reason',
    'generated_at',
])]
class TicketAiSuggestion extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'generated_at' => 'immutable_datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}

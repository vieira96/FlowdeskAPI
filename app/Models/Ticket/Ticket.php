<?php

namespace App\Models\Ticket;

use App\Models\Ai\TicketAiSuggestion;
use App\Models\Team\Team;
use App\Models\Team\TeamCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'title',
    'description',
    'status',
    'priority',
    'category_id',
    'team_id',
    'requester_id',
    'assignee_id',
    'first_response_due_at',
    'first_responded_at',
    'resolution_due_at',
    'resolved_at',
])]
class Ticket extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'first_response_due_at' => 'immutable_datetime',
            'first_responded_at' => 'immutable_datetime',
            'resolution_due_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TeamCategory::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->latest();
    }

    public function aiSuggestion(): HasOne
    {
        return $this->hasOne(TicketAiSuggestion::class);
    }
}

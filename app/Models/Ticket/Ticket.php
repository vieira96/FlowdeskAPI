<?php

namespace App\Models\Ticket;

use App\Models\Team\Team;
use App\Models\Team\TeamCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'status', 'priority', 'category_id', 'team_id', 'requester_id', 'assignee_id'])]
class Ticket extends Model
{
    use HasUuids;

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
}

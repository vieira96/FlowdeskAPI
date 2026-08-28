<?php

namespace App\Models\Team;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'created_by'])]
class Team extends Model
{
    use HasUuids;

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('id')
            ->withTimestamps();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TeamCategory::class);
    }
}

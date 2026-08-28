<?php

namespace App\Models\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Role extends Model
{
    use HasUuids;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

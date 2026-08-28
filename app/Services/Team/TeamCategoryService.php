<?php

namespace App\Services\Team;

use App\Models\Team\TeamCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeamCategoryService
{
    public function paginate(): LengthAwarePaginator
    {
        return TeamCategory::query()
            ->with('team')
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate();
    }

    public function create(array $data): TeamCategory
    {
        return TeamCategory::query()->create($data)->load('team');
    }
}

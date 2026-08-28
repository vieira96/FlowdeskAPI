<?php

namespace App\Services\Ticket;

use App\Models\Team\TeamCategory;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with(['category', 'team'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId));

        if ($user->role?->slug !== 'admin') {
            $query->whereIn('team_id', $user->teams()->select('teams.id'));
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function create(array $data, User $requester): Ticket
    {
        $category = TeamCategory::query()
            ->whereKey($data['category_id'])
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'A categoria selecionada não está disponível.',
            ]);
        }

        return Ticket::query()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
            'category_id' => $category->id,
            'team_id' => $category->team_id,
            'requester_id' => $requester->id,
        ])->load(['category', 'team']);
    }
}

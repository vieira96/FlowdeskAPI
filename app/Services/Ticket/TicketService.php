<?php

namespace App\Services\Ticket;

use App\Models\Team\TeamCategory;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with(['category', 'team', 'assignee'])
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
        ])->load(['category', 'team', 'assignee']);
    }

    public function assume(Ticket $ticket, User $agent): Ticket
    {
        if ($ticket->assignee_id !== null) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Este ticket já possui um agente responsável.',
            ]);
        }

        if ($ticket->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => 'Somente tickets abertos podem ser assumidos.',
            ]);
        }

        $ticket->update([
            'assignee_id' => $agent->id,
            'status' => 'in_progress',
        ]);

        return $ticket->load(['category', 'team', 'assignee']);
    }

    public function changeStatus(Ticket $ticket, string $status): Ticket
    {
        $transitions = [
            'in_progress' => ['resolved'],
            'resolved' => ['closed'],
        ];

        if (! in_array($status, $transitions[$ticket->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A transição de {$ticket->status} para {$status} não é permitida.",
            ]);
        }

        $ticket->update(['status' => $status]);

        return $ticket->load(['category', 'team', 'assignee']);
    }

    public function addComment(Ticket $ticket, User $agent, string $body): TicketComment
    {
        return TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => $body,
        ])->load('author');
    }
}

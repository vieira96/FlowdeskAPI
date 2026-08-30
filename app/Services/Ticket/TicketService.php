<?php

namespace App\Services\Ticket;

use App\Jobs\Ai\GenerateTicketHintJob;
use App\Models\Team\TeamCategory;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketAssignment;
use App\Models\Ticket\TicketComment;
use App\Models\User;
use App\Services\Notification\TicketNotificationService;
use App\Services\Sla\TicketSlaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        private readonly TicketNotificationService $ticketNotificationService,
        private readonly TicketSlaService $ticketSlaService,
    ) {}

    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with(['category', 'team', 'assignee'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId));

        if ($user->role?->slug === 'requester') {
            $query->where('requester_id', $user->id);
        } elseif ($user->role?->slug !== 'admin') {
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

        $priority = $data['priority'] ?? 'medium';
        $deadlines = config('ai.ticket_hints.enabled')
            ? []
            : $this->ticketSlaService->deadlinesFor($priority);

        $ticket = Ticket::query()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $priority,
            'status' => 'open',
            'category_id' => $category->id,
            'team_id' => $category->team_id,
            'requester_id' => $requester->id,
            ...$deadlines,
        ]);

        if (config('ai.ticket_hints.enabled')) {
            GenerateTicketHintJob::dispatch($ticket->id);
        } else {
            $ticket->load('team.agents');
            $this->ticketNotificationService->notifyTeamForNewTicket($ticket);
        }

        return $ticket->load(['category', 'team', 'assignee']);
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

        $ticket = $this->assign($ticket, $agent, 'manual');
        $this->ticketNotificationService->notifyTicketAssumed($ticket);

        return $ticket;
    }

    public function autoAssign(Ticket $ticket, User $agent): Ticket
    {
        $ticket = $this->assign($ticket, $agent, 'automatic');
        $this->ticketNotificationService->notifyTicketAutomaticallyAssigned($ticket);

        return $ticket;
    }

    public function requestHumanAssistance(Ticket $ticket): Ticket
    {
        if ($ticket->human_assistance_requested_at !== null) {
            return $ticket;
        }

        if ($ticket->aiSuggestion?->status !== 'published') {
            throw ValidationException::withMessages([
                'ticket' => 'A ajuda humana só pode ser solicitada após a orientação da IA.',
            ]);
        }

        $ticket->update([
            'human_assistance_requested_at' => now(),
            ...$this->ticketSlaService->deadlinesFor($ticket->priority),
        ]);
        $ticket->load(['category', 'team.agents', 'assignee']);
        $this->ticketNotificationService->notifyTeamForHumanAssistance($ticket);

        return $ticket;
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

        $ticket->update([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? $this->ticketSlaService->resolvedAt() : $ticket->resolved_at,
        ]);

        $ticket->load(['category', 'team', 'assignee', 'requester']);
        $this->ticketNotificationService->notifyStatusChanged($ticket);

        return $ticket;
    }

    public function addComment(Ticket $ticket, User $user, string $body): TicketComment
    {
        return TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'source' => $user->role?->slug === 'requester' ? 'requester' : 'agent',
            'body' => $body,
        ])->load('author');
    }

    private function assign(Ticket $ticket, User $agent, string $source): Ticket
    {
        return DB::transaction(function () use ($ticket, $agent, $source): Ticket {
            $ticket->update([
                'assignee_id' => $agent->id,
                'status' => 'in_progress',
                'first_responded_at' => $this->ticketSlaService->firstResponseAt(),
            ]);

            TicketAssignment::query()->create([
                'ticket_id' => $ticket->id,
                'agent_id' => $agent->id,
                'team_id' => $ticket->team_id,
                'source' => $source,
                'assigned_at' => now(),
            ]);

            return $ticket->load(['category', 'team', 'assignee', 'requester']);
        });
    }
}

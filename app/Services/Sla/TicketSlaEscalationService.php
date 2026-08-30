<?php

namespace App\Services\Sla;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketSlaEscalation;
use App\Models\User;
use App\Services\Notification\TicketNotificationService;
use App\Services\Ticket\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketSlaEscalationService
{
    public function __construct(
        private readonly TicketNotificationService $ticketNotificationService,
        private readonly TicketService $ticketService,
    ) {}

    /** @return array{halfway_notifications: int, automatic_assignments: int} */
    public function processTicket(string $ticketId): array
    {
        return DB::transaction(function () use ($ticketId): array {
            $ticket = Ticket::query()
                ->with('team.agents')
                ->lockForUpdate()
                ->find($ticketId);

            if ($ticket === null
                || $ticket->status !== 'open'
                || $ticket->assignee_id !== null
                || $ticket->first_response_due_at === null
                || $ticket->first_response_due_at->lessThanOrEqualTo(now())) {
                return ['halfway_notifications' => 0, 'automatic_assignments' => 0];
            }

            $policy = config("sla.priorities.{$ticket->priority}");

            if ($policy === null) {
                Log::warning('Ticket ignored by SLA escalation due to an unknown priority.', [
                    'ticket_id' => $ticket->id,
                    'priority' => $ticket->priority,
                ]);

                return ['halfway_notifications' => 0, 'automatic_assignments' => 0];
            }

            $totalSeconds = $policy['first_response_minutes'] * 60;
            $dueAt = CarbonImmutable::instance($ticket->first_response_due_at);
            $startedAt = $dueAt->subSeconds($totalSeconds);
            $now = CarbonImmutable::now();
            $halfwayAt = $startedAt->addSeconds((int) floor($totalSeconds / 2));
            $autoAssignmentAt = $dueAt->subSeconds((int) floor($totalSeconds * 0.2));
            $halfwayNotifications = 0;

            if ($now->greaterThanOrEqualTo($halfwayAt)
                && $this->recordEscalation($ticket, 'first_response_halfway_notified')) {
                $this->ticketNotificationService->notifyTeamForFirstResponseSlaHalfway($ticket);
                $halfwayNotifications = 1;
            }

            if ($now->lessThan($autoAssignmentAt)) {
                return ['halfway_notifications' => $halfwayNotifications, 'automatic_assignments' => 0];
            }

            $agent = $this->leastLoadedAgent($ticket);

            if ($agent === null) {
                $this->recordEscalation($ticket, 'first_response_auto_assignment_unavailable');
                Log::warning('Ticket could not be automatically assigned because its team has no agents.', [
                    'ticket_id' => $ticket->id,
                    'team_id' => $ticket->team_id,
                ]);

                return ['halfway_notifications' => $halfwayNotifications, 'automatic_assignments' => 0];
            }

            if (! $this->recordEscalation($ticket, 'first_response_auto_assigned')) {
                return ['halfway_notifications' => $halfwayNotifications, 'automatic_assignments' => 0];
            }

            $this->ticketService->autoAssign($ticket, $agent);

            return ['halfway_notifications' => $halfwayNotifications, 'automatic_assignments' => 1];
        });
    }

    private function leastLoadedAgent(Ticket $ticket): ?User
    {
        return $ticket->team->agents()
            ->withCount([
                'assignedTickets as active_tickets_count' => fn ($query) => $query->whereIn('status', ['open', 'in_progress']),
            ])
            ->orderBy('active_tickets_count')
            ->orderBy('users.id')
            ->first();
    }

    private function recordEscalation(Ticket $ticket, string $type): bool
    {
        $escalation = TicketSlaEscalation::query()->firstOrCreate(
            ['ticket_id' => $ticket->id, 'type' => $type],
            ['triggered_at' => now()],
        );

        return $escalation->wasRecentlyCreated;
    }
}

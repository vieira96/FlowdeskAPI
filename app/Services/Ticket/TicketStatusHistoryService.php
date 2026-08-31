<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TicketStatusHistoryService
{
    public function record(Ticket $ticket, ?User $actor, string $oldStatus, string $newStatus): TicketStatusHistory
    {
        return TicketStatusHistory::query()->create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor?->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_at' => now(),
        ]);
    }

    /** @return Collection<int, TicketStatusHistory> */
    public function history(Ticket $ticket): Collection
    {
        return $ticket->statusHistories()->with('actor')->latest('changed_at')->get();
    }
}

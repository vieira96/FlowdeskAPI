<?php

namespace App\Policies\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function create(User $user): bool
    {
        return $user->role?->slug === 'requester';
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['admin', 'agent', 'requester'], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role?->slug === 'admin') {
            return true;
        }

        if ($user->role?->slug === 'requester') {
            return $ticket->requester_id === $user->id;
        }

        return $user->role?->slug === 'agent'
            && $user->teams()->whereKey($ticket->team_id)->exists();
    }

    public function assume(User $user, Ticket $ticket): bool
    {
        return $this->isAgentFromTicketTeam($user, $ticket);
    }

    public function updateStatus(User $user, Ticket $ticket): bool
    {
        if ($user->role?->slug === 'requester') {
            return $ticket->requester_id === $user->id;
        }

        return $this->isAgentFromTicketTeam($user, $ticket) && $ticket->assignee_id === $user->id;
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        if ($user->role?->slug === 'requester') {
            return $ticket->requester_id === $user->id;
        }

        return $this->updateStatus($user, $ticket);
    }

    public function requestHumanAssistance(User $user, Ticket $ticket): bool
    {
        return $user->role?->slug === 'requester' && $ticket->requester_id === $user->id;
    }

    private function isAgentFromTicketTeam(User $user, Ticket $ticket): bool
    {
        return $user->role?->slug === 'agent'
            && $user->teams()->whereKey($ticket->team_id)->exists();
    }
}

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
        return in_array($user->role?->slug, ['admin', 'agent'], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role?->slug === 'admin') {
            return true;
        }

        return $user->role?->slug === 'agent'
            && $user->teams()->whereKey($ticket->team_id)->exists();
    }
}

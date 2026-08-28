<?php

namespace App\Services\Notification;

use App\Models\Ticket\Ticket;
use App\Notifications\Ticket\TicketActivityNotification;

class TicketNotificationService
{
    public function notifyTicketAssumed(Ticket $ticket): void
    {
        $ticket->requester->notify(new TicketActivityNotification(
            ticket: $ticket,
            event: 'ticket.assumed',
            message: 'Seu ticket foi assumido por um agente.',
        ));
    }

    public function notifyStatusChanged(Ticket $ticket): void
    {
        $message = match ($ticket->status) {
            'resolved' => 'Seu ticket foi marcado como resolvido.',
            'closed' => 'Seu ticket foi encerrado.',
            default => 'O status do seu ticket foi atualizado.',
        };

        $ticket->requester->notify(new TicketActivityNotification(
            ticket: $ticket,
            event: 'ticket.status_changed',
            message: $message,
        ));
    }
}

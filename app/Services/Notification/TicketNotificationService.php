<?php

namespace App\Services\Notification;

use App\Models\Ticket\Ticket;
use App\Notifications\Ticket\TicketActivityNotification;

class TicketNotificationService
{
    public function notifyTeamForNewTicket(Ticket $ticket): void
    {
        $this->notifyTeam(
            $ticket,
            event: 'ticket.created_without_ai_hint',
            message: 'Novo ticket aguardando atendimento da equipe.',
        );
    }

    public function notifyTeamForHumanAssistance(Ticket $ticket): void
    {
        $this->notifyTeam(
            $ticket,
            event: 'ticket.human_assistance_requested',
            message: 'O solicitante pediu atendimento humano neste ticket.',
        );
    }

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

    public function notifyAiHintPublished(Ticket $ticket): void
    {
        $ticket->requester->notify(new TicketActivityNotification(
            ticket: $ticket,
            event: 'ticket.ai_hint_published',
            message: 'O Assistente IA deixou uma orientação no seu ticket.',
        ));
    }

    private function notifyTeam(Ticket $ticket, string $event, string $message): void
    {
        $ticket->loadMissing('team.agents');

        $ticket->team->agents->each(fn ($agent) => $agent->notify(new TicketActivityNotification(
            ticket: $ticket,
            event: $event,
            message: $message,
        )));
    }
}

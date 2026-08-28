<?php

namespace App\Notifications\Ticket;

use App\Models\Ticket\Ticket;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketActivityNotification extends Notification
{
    public function __construct(
        private readonly Ticket $ticket,
        private readonly string $event,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastType(): string
    {
        return 'ticket.activity';
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'event' => $this->event,
            'message' => $this->message,
            'ticket' => [
                'id' => $this->ticket->id,
                'title' => $this->ticket->title,
                'status' => $this->ticket->status,
                'priority' => $this->ticket->priority,
            ],
        ];
    }
}

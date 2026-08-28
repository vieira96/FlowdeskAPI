<?php

namespace App\Jobs\Ai;

use App\Models\Ticket\Ticket;
use App\Services\Ai\TicketHintService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTicketHintJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly string $ticketId) {}

    public function handle(TicketHintService $ticketHintService): void
    {
        if (! config('ai.ticket_hints.enabled')) {
            return;
        }

        $ticket = Ticket::query()->find($this->ticketId);

        if ($ticket !== null) {
            $ticketHintService->generateFor($ticket);
        }
    }
}

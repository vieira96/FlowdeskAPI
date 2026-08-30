<?php

namespace App\Jobs\Ticket;

use App\Services\Sla\TicketSlaEscalationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTicketSlaEscalationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct(public readonly string $ticketId) {}

    public function uniqueId(): string
    {
        return $this->ticketId;
    }

    public function handle(TicketSlaEscalationService $ticketSlaEscalationService): void
    {
        $ticketSlaEscalationService->processTicket($this->ticketId);
    }
}

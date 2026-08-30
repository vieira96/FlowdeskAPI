<?php

namespace App\Jobs\Ticket;

use App\Models\Ticket\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchTicketSlaEscalationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 55;

    public function uniqueId(): string
    {
        return 'ticket-sla-escalations';
    }

    public function handle(): void
    {
        Ticket::query()
            ->where('status', 'open')
            ->whereNull('assignee_id')
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '>', now())
            ->select('id')
            ->chunkById(100, function ($tickets): void {
                $tickets->each(fn (Ticket $ticket) => ProcessTicketSlaEscalationJob::dispatch($ticket->id));
            });
    }
}

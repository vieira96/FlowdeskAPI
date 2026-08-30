<?php

namespace App\Console\Commands\Ticket;

use App\Jobs\Ticket\DispatchTicketSlaEscalationsJob;
use Illuminate\Console\Command;

class ProcessTicketSlaEscalations extends Command
{
    protected $signature = 'tickets:escalate-sla';

    protected $description = 'Notifica equipes e atribui tickets próximos do vencimento da primeira resposta.';

    public function handle(): int
    {
        DispatchTicketSlaEscalationsJob::dispatch();

        $this->info('Busca de tickets para escalonamento enviada para a fila.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Sla;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use LogicException;

class TicketSlaService
{
    /** @return array{first_response_due_at: CarbonImmutable, resolution_due_at: CarbonImmutable} */
    public function deadlinesFor(string $priority): array
    {
        $policy = config("sla.priorities.{$priority}");

        if ($policy === null) {
            throw new LogicException("Nenhuma política de SLA foi definida para a prioridade {$priority}.");
        }

        $startedAt = CarbonImmutable::now();

        return [
            'first_response_due_at' => $startedAt->addMinutes($policy['first_response_minutes']),
            'resolution_due_at' => $startedAt->addMinutes($policy['resolution_minutes']),
        ];
    }

    public function firstResponseAt(): Carbon
    {
        return now();
    }

    public function resolvedAt(): Carbon
    {
        return now();
    }
}

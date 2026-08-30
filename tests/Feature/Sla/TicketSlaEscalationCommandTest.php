<?php

namespace Tests\Feature\Sla;

use App\Jobs\Ticket\DispatchTicketSlaEscalationsJob;
use App\Jobs\Ticket\ProcessTicketSlaEscalationJob;
use App\Models\Access\Role;
use App\Models\Team\Team;
use App\Models\Team\TeamCategory;
use App\Models\Team\TeamMember;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketSlaEscalationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_only_queues_the_ticket_search_job(): void
    {
        Queue::fake();

        $this->artisan('tickets:escalate-sla')->assertSuccessful();

        Queue::assertPushed(DispatchTicketSlaEscalationsJob::class, 1);
    }

    public function test_the_search_job_queues_an_individual_job_with_the_ticket_id(): void
    {
        [$team, , , $requester, $category] = $this->teamContext();
        $ticket = $this->ticket($team, $category, $requester, CarbonImmutable::now()->addHour());

        Queue::fake();

        app(DispatchTicketSlaEscalationsJob::class)->handle();

        Queue::assertPushed(
            ProcessTicketSlaEscalationJob::class,
            fn (ProcessTicketSlaEscalationJob $job) => $job->ticketId === $ticket->id,
        );
    }

    public function test_it_notifies_every_team_agent_once_at_half_of_the_first_response_sla(): void
    {
        [$team, $firstAgent, $secondAgent, $requester, $category] = $this->teamContext();
        $startedAt = CarbonImmutable::parse('2026-08-30 10:00:00', 'UTC');
        $ticket = $this->ticket($team, $category, $requester, $startedAt->addHour());

        $this->travelTo($startedAt->addMinutes(30));

        try {
            $this->artisan('tickets:escalate-sla')->assertSuccessful();
            $this->artisan('tickets:escalate-sla')->assertSuccessful();

            $this->assertSame(1, $this->notificationCount($firstAgent, 'ticket.sla_first_response_halfway'));
            $this->assertSame(1, $this->notificationCount($secondAgent, 'ticket.sla_first_response_halfway'));
            $this->assertDatabaseHas('ticket_sla_escalations', [
                'ticket_id' => $ticket->id,
                'type' => 'first_response_halfway_notified',
            ]);
        } finally {
            $this->travelBack();
        }
    }

    public function test_it_automatically_assigns_the_least_loaded_agent_at_eighty_percent_of_the_sla(): void
    {
        [$team, $busyAgent, $availableAgent, $requester, $category] = $this->teamContext();
        $startedAt = CarbonImmutable::parse('2026-08-30 10:00:00', 'UTC');
        $this->ticket($team, $category, $requester, $startedAt->addHours(2), [
            'assignee_id' => $busyAgent->id,
            'status' => 'in_progress',
        ]);
        $ticket = $this->ticket($team, $category, $requester, $startedAt->addHour());

        $this->travelTo($startedAt->addMinutes(48));

        try {
            $this->artisan('tickets:escalate-sla')->assertSuccessful();

            $ticket->refresh();
            $this->assertSame($availableAgent->id, $ticket->assignee_id);
            $this->assertSame('in_progress', $ticket->status);
            $this->assertNotNull($ticket->first_responded_at);
            $this->assertDatabaseHas('ticket_assignments', [
                'ticket_id' => $ticket->id,
                'agent_id' => $availableAgent->id,
                'team_id' => $team->id,
                'source' => 'automatic',
            ]);
            $this->assertDatabaseHas('ticket_sla_escalations', [
                'ticket_id' => $ticket->id,
                'type' => 'first_response_auto_assigned',
            ]);
            $this->assertSame(1, $this->notificationCount($availableAgent, 'ticket.auto_assigned'));
            $this->assertSame(1, $this->notificationCount($requester, 'ticket.assumed'));
        } finally {
            $this->travelBack();
        }
    }

    public function test_it_does_not_automatically_assign_a_ticket_after_the_first_response_sla_has_expired(): void
    {
        [$team, $firstAgent, $secondAgent, $requester, $category] = $this->teamContext();
        $startedAt = CarbonImmutable::parse('2026-08-30 10:00:00', 'UTC');
        $ticket = $this->ticket($team, $category, $requester, $startedAt->addHour());

        $this->travelTo($startedAt->addHour()->addSecond());

        try {
            $this->artisan('tickets:escalate-sla')->assertSuccessful();

            $ticket->refresh();
            $this->assertNull($ticket->assignee_id);
            $this->assertSame('open', $ticket->status);
            $this->assertSame(0, $this->notificationCount($firstAgent, 'ticket.sla_first_response_halfway'));
            $this->assertSame(0, $this->notificationCount($secondAgent, 'ticket.sla_first_response_halfway'));
            $this->assertDatabaseMissing('ticket_sla_escalations', ['ticket_id' => $ticket->id]);
        } finally {
            $this->travelBack();
        }
    }

    /** @return array{Team, User, User, User, TeamCategory} */
    private function teamContext(): array
    {
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        $firstAgent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $secondAgent = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'agent')->value('id'),
        ]);
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();

        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $firstAgent->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $secondAgent->id]);

        return [$team, $firstAgent, $secondAgent, $requester, $category];
    }

    /** @param array<string, mixed> $attributes */
    private function ticket(Team $team, TeamCategory $category, User $requester, CarbonImmutable $firstResponseDueAt, array $attributes = []): Ticket
    {
        return Ticket::query()->create([
            'title' => 'Ticket para escalonamento de SLA',
            'description' => 'Ticket criado para validar o escalonamento automático.',
            'status' => 'open',
            'priority' => 'high',
            'category_id' => $category->id,
            'team_id' => $team->id,
            'requester_id' => $requester->id,
            'first_response_due_at' => $firstResponseDueAt,
            'resolution_due_at' => $firstResponseDueAt->addHours(7),
            ...$attributes,
        ]);
    }

    private function notificationCount(User $user, string $event): int
    {
        return $user->notifications()->where('data->event', $event)->count();
    }
}

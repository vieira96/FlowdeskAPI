<?php

namespace Tests\Feature\Api\Notification;

use App\Models\Team\Team;
use App\Models\Team\TeamCategory;
use App\Models\Team\TeamMember;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Notifications\Ticket\TicketActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_receives_a_database_and_broadcast_notification_when_ticket_is_assumed(): void
    {
        Event::fake([BroadcastNotificationCreated::class]);
        $agent = User::query()->where('email', 'agent@agent.com')->firstOrFail();
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $team = Team::query()->where('name', 'Suporte de TI')->firstOrFail();
        $category = TeamCategory::query()->where('name', 'Impressora')->firstOrFail();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $ticket = Ticket::query()->create([
            'title' => 'Impressora parada',
            'description' => 'A impressora não responde.',
            'category_id' => $category->id,
            'team_id' => $team->id,
            'requester_id' => $requester->id,
        ]);

        $this->withToken($agent->createToken('test')->plainTextToken)
            ->postJson("/api/v1/tickets/{$ticket->id}/assume")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $requester->id,
            'type' => TicketActivityNotification::class,
        ]);
        Event::assertDispatched(BroadcastNotificationCreated::class);
    }

    public function test_a_user_can_list_and_mark_only_their_own_notification_as_read(): void
    {
        $requester = User::query()->where('email', 'requester@requester.com')->firstOrFail();
        $requester->notify(new TicketActivityNotification(
            Ticket::query()->create([
                'title' => 'Ticket de teste',
                'description' => 'Notificação de teste.',
                'category_id' => TeamCategory::query()->where('name', 'Impressora')->value('id'),
                'team_id' => Team::query()->where('name', 'Suporte de TI')->value('id'),
                'requester_id' => $requester->id,
            ]),
            'ticket.assumed',
            'Seu ticket foi assumido por um agente.',
        ));
        $notification = $requester->notifications()->firstOrFail();
        $token = $requester->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.data.event', 'ticket.assumed');

        $this->withToken($token)
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);
    }
}

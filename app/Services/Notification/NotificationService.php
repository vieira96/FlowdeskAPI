<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function paginate(User $user): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate();
    }

    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();
        $notification->refresh();

        return $notification;
    }
}

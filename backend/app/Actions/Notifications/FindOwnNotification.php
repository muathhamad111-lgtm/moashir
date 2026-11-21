<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Identity\User;
use App\Models\Notifications\Notification;

final class FindOwnNotification
{
    public function handle(User $user, string $notificationId): Notification
    {
        return Notification::query()->ownedBy($user->id)->whereKey($notificationId)->firstOrFail();
    }
}

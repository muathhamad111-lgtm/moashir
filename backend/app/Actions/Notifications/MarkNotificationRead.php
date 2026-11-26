<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Notifications\Notification;
use Illuminate\Support\Facades\Date;

final class MarkNotificationRead
{
    public function handle(Notification $notification): Notification
    {
        Notification::query()->whereKey($notification->id)->unread()->update(['read_at' => Date::now()]);

        return $notification->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Notifications\Notification;
use Illuminate\Support\Facades\Date;

final class ArchiveNotification
{
    public function handle(Notification $notification): Notification
    {
        Notification::query()->whereKey($notification->id)->inInbox()->update(['archived_at' => Date::now()]);

        return $notification->refresh();
    }
}

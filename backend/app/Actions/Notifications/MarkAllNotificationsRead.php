<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final class MarkAllNotificationsRead
{
    public function handle(User $user, ?NotificationCategory $category): int
    {
        return Notification::query()
            ->ownedBy($user->id)
            ->inInbox()
            ->unread()
            ->when($category instanceof NotificationCategory, static fn (Builder $query): Builder => $query->where('category', $category))
            ->update(['read_at' => Date::now()]);
    }
}

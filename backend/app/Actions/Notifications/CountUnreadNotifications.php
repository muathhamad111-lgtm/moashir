<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\UnreadNotificationCounts;
use App\Enums\Notifications\NotificationCategory;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Support\ScalarValue;

final class CountUnreadNotifications
{
    public function handle(User $user): UnreadNotificationCounts
    {
        $counts = Notification::query()
            ->ownedBy($user->id)
            ->inInbox()
            ->unread()
            ->toBase()
            ->selectRaw('category, count(*) as unread')
            ->groupBy('category')
            ->pluck('unread', 'category');

        $byCategory = [];

        foreach (NotificationCategory::cases() as $category) {
            $byCategory[$category->value] = ScalarValue::int($counts->get($category->value), 0);
        }

        return new UnreadNotificationCounts(array_sum($byCategory), $byCategory);
    }
}

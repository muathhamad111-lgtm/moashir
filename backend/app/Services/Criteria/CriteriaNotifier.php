<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use App\Notifications\Criteria\CriteriaNotification;
use Illuminate\Support\Facades\Notification;

final class CriteriaNotifier
{
    public function notify(CriteriaNotice $notice, ?int $actorId): void
    {
        $recipients = User::query()
            ->role(array_map(static fn (RoleKey $role): string => $role->value, $notice->recipientRoles))
            ->where('status', UserStatus::Active)
            ->when($actorId !== null, static fn ($query) => $query->whereKeyNot($actorId))
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new CriteriaNotification($notice));
        }
    }
}

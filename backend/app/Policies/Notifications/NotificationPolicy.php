<?php

declare(strict_types=1);

namespace App\Policies\Notifications;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use Illuminate\Auth\Access\Response;

final class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionKey::NotificationsView->value);
    }

    public function update(User $user, Notification $notification): Response
    {
        return $notification->isOwnedBy($user->id) && $this->viewAny($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewCatalog(User $user): bool
    {
        return $user->can(PermissionKey::SettingsNotificationsManage->value);
    }
}

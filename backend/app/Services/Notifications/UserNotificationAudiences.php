<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationAudience;
use App\Models\Identity\User;

final class UserNotificationAudiences
{
    /**
     * @return list<NotificationAudience>
     */
    public function of(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [NotificationAudience::AccountHolder, NotificationAudience::SuperAdmin];
        }

        $audiences = [NotificationAudience::AccountHolder];

        foreach ($user->loadMissing('roles')->roleKeys() as $role) {
            $audiences = [...$audiences, ...$this->ofRole($role)];
        }

        if ($user->can(PermissionKey::ContactMessagesManage->value)) {
            $audiences[] = NotificationAudience::ContactHandler;
        }

        return $audiences;
    }

    /**
     * @return list<NotificationAudience>
     */
    private function ofRole(RoleKey $role): array
    {
        $audience = NotificationAudience::from($role->value);

        return $role === RoleKey::Assessor ? [$audience, NotificationAudience::CommitteeAssessor] : [$audience];
    }
}

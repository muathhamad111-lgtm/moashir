<?php

declare(strict_types=1);

namespace App\Policies\Platform;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class PlatformReadinessPolicy
{
    public function viewDetails(User $user): bool
    {
        return $user->can(PermissionKey::SettingsGeneralManage->value);
    }
}

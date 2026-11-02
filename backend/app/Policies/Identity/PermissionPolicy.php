<?php

declare(strict_types=1);

namespace App\Policies\Identity;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class PermissionPolicy
{
    public function updateLabels(User $actor): bool
    {
        return $actor->can(PermissionKey::RolesMatrixManage->value);
    }
}

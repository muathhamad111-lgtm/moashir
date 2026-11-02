<?php

declare(strict_types=1);

namespace App\Policies\Entities;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class EmailChangeRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesEmailChangeApprove->value);
    }

    public function decide(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesEmailChangeApprove->value);
    }
}

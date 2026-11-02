<?php

declare(strict_types=1);

namespace App\Policies\Entities;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class CityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesManage->value);
    }

    public function update(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesManage->value);
    }
}

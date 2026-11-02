<?php

declare(strict_types=1);

namespace App\Policies\Criteria;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class CriteriaNodePolicy
{
    public function view(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaView->value);
    }

    public function update(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaManage->value);
    }

    public function delete(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaManage->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\Entities;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class PrivacyRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }

    public function view(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }

    public function decide(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }

    public function lookupEntities(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\Criteria;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class CriteriaVersionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaView->value);
    }

    public function view(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaManage->value);
    }

    public function manageTree(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaManage->value);
    }

    public function discard(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaManage->value);
    }

    public function correctText(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaManage->value);
    }

    public function publish(User $actor): bool
    {
        return $actor->can(PermissionKey::CriteriaPublish->value);
    }

    public function viewPublished(User $actor): bool
    {
        return $actor->can(PermissionKey::AssessmentCriteriaView->value);
    }
}

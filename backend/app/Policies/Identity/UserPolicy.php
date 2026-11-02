<?php

declare(strict_types=1);

namespace App\Policies\Identity;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::UsersView->value);
    }

    public function view(User $actor, User $target): Response
    {
        return $this->authorizeOn($actor, $target, PermissionKey::UsersView);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionKey::UsersManage->value);
    }

    public function update(User $actor, User $target): Response
    {
        return $this->authorizeOn($actor, $target, PermissionKey::UsersManage);
    }

    public function changeStatus(User $actor, User $target): Response
    {
        return $this->authorizeOn($actor, $target, PermissionKey::UsersManage);
    }

    public function resetMfa(User $actor, User $target): Response
    {
        return $this->authorizeOn($actor, $target, PermissionKey::UsersManage);
    }

    private function authorizeOn(User $actor, User $target, PermissionKey $permission): Response
    {
        if ($target->isSuperAdmin()) {
            return Response::denyAsNotFound();
        }

        return $actor->can($permission->value) ? Response::allow() : Response::deny();
    }
}

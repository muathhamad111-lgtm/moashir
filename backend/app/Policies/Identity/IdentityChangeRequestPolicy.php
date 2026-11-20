<?php

declare(strict_types=1);

namespace App\Policies\Identity;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class IdentityChangeRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::UsersIdentityChangesReview->value);
    }

    public function view(User $actor): bool
    {
        return $actor->can(PermissionKey::UsersIdentityChangesReview->value);
    }

    public function decide(User $actor, IdentityChangeRequest $request): Response
    {
        if (! $actor->can(PermissionKey::UsersIdentityChangesReview->value)) {
            return Response::deny();
        }

        return $request->involves($actor->id) ? Response::deny(__('errors.identity_change.own_request')) : Response::allow();
    }

    public function cancel(User $actor, IdentityChangeRequest $request): Response
    {
        return $request->involves($actor->id) ? Response::allow() : Response::denyAsNotFound();
    }
}

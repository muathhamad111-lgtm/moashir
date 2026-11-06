<?php

declare(strict_types=1);

namespace App\Policies\Entities;

use App\Enums\Entities\EntityAccess;
use App\Enums\Identity\PermissionKey;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use Illuminate\Auth\Access\Response;

final readonly class EntityPolicy
{
    public function __construct(private EntityRecordScope $scope) {}

    public function viewOwn(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::EntityProfileView->value);
    }

    public function updateOwn(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::EntityProfileEdit->value);
    }

    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesView->value);
    }

    public function viewDeleted(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesDelete->value);
    }

    public function view(User $actor, Entity $entity): Response
    {
        if ($entity->trashed() && ! $actor->can(PermissionKey::EntitiesDelete->value)) {
            return Response::denyAsNotFound();
        }

        if (! $actor->can(PermissionKey::EntitiesView->value)) {
            return Response::deny();
        }

        return $this->scope->accessOf($actor) === EntityAccess::None ? Response::denyAsNotFound() : Response::allow();
    }

    public function viewLogo(User $actor, Entity $entity): Response
    {
        if ($actor->entity_id === null) {
            return $this->view($actor, $entity);
        }

        return $actor->entity_id === $entity->id && $this->viewOwn($actor) ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewProfileChanges(User $actor, Entity $entity): Response
    {
        $view = $this->view($actor, $entity);

        if ($view->denied()) {
            return $view;
        }

        return $this->scope->accessOf($actor) === EntityAccess::Full ? Response::allow() : Response::deny();
    }

    public function update(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesManage->value) && $this->scope->accessOf($actor) === EntityAccess::Full;
    }

    public function deactivate(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesDeactivate->value);
    }

    public function reactivate(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesReactivate->value);
    }

    public function resendVerification(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesVerificationResend->value);
    }

    public function delete(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesDelete->value);
    }

    public function restore(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesDelete->value);
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function export(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesExport->value) && $this->scope->accessOf($actor) === EntityAccess::Full;
    }

    public function exportPersonalData(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }
}

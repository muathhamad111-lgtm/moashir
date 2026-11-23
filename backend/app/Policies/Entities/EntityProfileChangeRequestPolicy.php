<?php

declare(strict_types=1);

namespace App\Policies\Entities;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class EntityProfileChangeRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->approves($actor);
    }

    public function view(User $actor): bool
    {
        return $this->approves($actor);
    }

    public function decide(User $actor): bool
    {
        return $this->approves($actor);
    }

    private function approves(User $actor): bool
    {
        return $actor->entity_id === null && $actor->can(PermissionKey::EntitiesProfileChangeApprove->value);
    }
}

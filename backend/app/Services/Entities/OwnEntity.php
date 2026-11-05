<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class OwnEntity
{
    public function of(User $user): Entity
    {
        if ($user->entity_id === null) {
            throw (new ModelNotFoundException)->setModel(Entity::class);
        }

        return Entity::query()->whereKey($user->entity_id)->firstOrFail();
    }

    public function editableOf(User $user, string $attemptedVerb): Entity
    {
        $entity = $this->of($user);

        if (! $entity->isActive()) {
            throw IllegalStateTransition::from($entity->status->value, $attemptedVerb);
        }

        return $entity;
    }
}

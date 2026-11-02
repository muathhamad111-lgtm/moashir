<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\Finance\PaymentRejected;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;

final readonly class PayingEntityGuard
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    public function entityOf(User $actor): Entity
    {
        $entity = $actor->entity_id === null ? null : Entity::withTrashed()->notErased()->find($actor->entity_id);

        if (! $entity instanceof Entity) {
            throw PaymentRejected::entityRequired();
        }

        $this->freezeGuard->assertNotFrozen($entity);

        if (! $entity->isActive()) {
            throw PaymentRejected::entityNotActive();
        }

        return $entity;
    }
}

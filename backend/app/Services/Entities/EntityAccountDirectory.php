<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Actions\Entities\VerifyEntityRegistration;
use App\Contracts\Identity\EntityAccounts;
use App\Data\Identity\EntitySessionSummary;
use App\Enums\Identity\EntityStanding;
use App\Models\Entities\Entity;
use App\Models\Identity\User;

final readonly class EntityAccountDirectory implements EntityAccounts
{
    public function __construct(private VerifyEntityRegistration $verifyRegistration) {}

    public function standingOf(User $user): EntityStanding
    {
        if ($user->entity_id === null) {
            return EntityStanding::NotAnEntity;
        }

        $entity = Entity::withTrashed()->find($user->entity_id);

        return match (true) {
            ! $entity instanceof Entity, $entity->trashed() => EntityStanding::Closed,
            $entity->isPendingVerification() => EntityStanding::PendingVerification,
            $entity->isDeactivated() => EntityStanding::Deactivated,
            default => EntityStanding::Active,
        };
    }

    public function confirmVerification(User $user): void
    {
        $this->verifyRegistration->handle($user);
    }

    public function sessionSummaryOf(User $user): ?EntitySessionSummary
    {
        $entity = $user->entity_id === null ? null : Entity::query()->find($user->entity_id);

        if (! $entity instanceof Entity) {
            return null;
        }

        return new EntitySessionSummary(
            $entity->id,
            $entity->reference(),
            $entity->name,
            $entity->status->value,
            $entity->hasCompleteProfile(),
        );
    }
}

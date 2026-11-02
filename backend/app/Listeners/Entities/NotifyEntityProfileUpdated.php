<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityProfileUpdated;
use App\Models\Identity\User;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityProfileUpdated
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityProfileUpdated $event): void
    {
        $entity = $event->entity;
        $replacements = ['entity' => $entity->name->toLocalizedText(), 'reference' => $entity->reference()];

        $this->notifier->toRole(
            RoleKey::SystemAdmin,
            EntityNotice::for(NotificationCode::EntityCoreDataEdited, EntityNotice::FOR_STAFF, $replacements, EntityLinks::record($entity->id))->inAppOnly(),
            $event->actorId,
        );

        if (! User::query()->whereKey($event->actorId)->where('entity_id', $entity->id)->exists()) {
            $this->notifier->toOwner(
                $entity,
                EntityNotice::for(NotificationCode::EntityCoreDataEdited, EntityNotice::FOR_ENTITY, $replacements, EntityLinks::ownProfile())->inAppOnly(),
            );
        }
    }
}

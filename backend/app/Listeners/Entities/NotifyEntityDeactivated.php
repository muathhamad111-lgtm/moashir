<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityDeactivated;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityDeactivated
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityDeactivated $event): void
    {
        $entity = $event->entity;
        $replacements = ['entity' => $entity->name->toLocalizedText(), 'reference' => $entity->reference()];

        $this->notifier->toOwner($entity, EntityNotice::for(NotificationCode::EntityDeactivated, EntityNotice::FOR_ENTITY, $replacements, null));
        $this->notifier->toRole(RoleKey::SystemAdmin, EntityNotice::for(NotificationCode::EntityDeactivated, EntityNotice::FOR_STAFF, $replacements, EntityLinks::record($entity->id)), $event->actorId);
    }
}

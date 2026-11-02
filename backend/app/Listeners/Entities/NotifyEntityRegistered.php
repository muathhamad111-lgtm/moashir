<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityRegistered;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityRegistered
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityRegistered $event): void
    {
        $entity = $event->entity;
        $replacements = ['entity' => $entity->name->toLocalizedText(), 'reference' => $entity->reference()];

        $this->notifier->toOwner($entity, EntityNotice::for(NotificationCode::EntityRegistered, EntityNotice::FOR_ENTITY, $replacements, EntityLinks::ownProfile()));
        $this->notifier->toRole(RoleKey::SystemAdmin, EntityNotice::for(NotificationCode::EntityRegistered, EntityNotice::FOR_STAFF, $replacements, EntityLinks::record($entity->id)), null);
    }
}

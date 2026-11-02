<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityRestored;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityRestored
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityRestored $event): void
    {
        $entity = $event->entity;

        $this->notifier->toRole(RoleKey::SystemAdmin, EntityNotice::for(NotificationCode::EntityDeletionChanged, 'staff_restored', [
            'entity' => $entity->name->toLocalizedText(),
            'reference' => $entity->reference(),
            'count' => (string) count($event->unfrozenRequestNumbers),
        ], EntityLinks::record($entity->id)), $event->actorId);
    }
}

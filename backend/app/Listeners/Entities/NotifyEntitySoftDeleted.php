<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntitySoftDeleted;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyEntitySoftDeleted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntitySoftDeleted $event): void
    {
        $entity = $event->entity;
        $replacements = [
            'entity' => $entity->name->toLocalizedText(),
            'reference' => $entity->reference(),
            'open_requests' => LocalizedText::fromChoice('quantities.open_requests', count($event->frozenRequestNumbers)),
        ];

        $this->notifier->toRole(RoleKey::SystemAdmin, EntityNotice::for(NotificationCode::EntityDeletionChanged, 'staff_deleted', $replacements, EntityLinks::deletedEntities()), $event->actorId);
        $this->notifier->toOwner($entity, EntityNotice::for(NotificationCode::EntityDeletionChanged, EntityNotice::FOR_ENTITY, $replacements, null)->mailOnly());
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityVerified;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityVerified
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityVerified $event): void
    {
        $entity = $event->entity;

        if (! $entity->isPendingReview()) {
            return;
        }

        $this->notifier->toRole(RoleKey::SystemAdmin, EntityNotice::for(
            NotificationCode::EntityAwaitingReview,
            EntityNotice::FOR_STAFF,
            ['entity' => $entity->name->toLocalizedText(), 'reference' => $entity->reference()],
            EntityLinks::record($entity->id),
        ), null);
    }
}

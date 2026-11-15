<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityApproved;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityApproved
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityApproved $event): void
    {
        $entity = $event->entity;
        $replacements = ['entity' => $entity->name->toLocalizedText()];

        $this->notifier->toOwner($entity, EntityNotice::for(NotificationCode::EntityRegistrationApproved, EntityNotice::FOR_ENTITY, $replacements, EntityLinks::signIn()));

        if (! $entity->hasCompleteProfile()) {
            $this->notifier->toOwner($entity, EntityNotice::for(NotificationCode::EntityProfileIncomplete, EntityNotice::FOR_ENTITY, $replacements, EntityLinks::ownProfile()));
        }
    }
}

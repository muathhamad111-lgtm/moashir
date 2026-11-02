<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityVerified;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityVerified
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityVerified $event): void
    {
        if ($event->entity->hasCompleteProfile()) {
            return;
        }

        $this->notifier->toOwner($event->entity, EntityNotice::for(
            NotificationCode::EntityProfileIncomplete,
            EntityNotice::FOR_ENTITY,
            ['entity' => $event->entity->name->toLocalizedText()],
            EntityLinks::ownProfile(),
        ));
    }
}

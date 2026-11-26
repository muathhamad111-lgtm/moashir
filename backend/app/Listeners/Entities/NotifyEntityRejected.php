<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityRejected;
use App\Services\Entities\EntityNotifier;

final readonly class NotifyEntityRejected
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityRejected $event): void
    {
        $this->notifier->toOwner($event->entity, EntityNotice::for(
            NotificationCode::EntityRegistrationRejected,
            EntityNotice::FOR_ENTITY,
            ['entity' => $event->entity->name->toLocalizedText(), 'reason' => $event->reason],
            null,
        )->mailOnly());
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityVerificationResent;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;

final readonly class NotifyEntityVerificationResent
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityVerificationResent $event): void
    {
        $this->notifier->toOwner($event->entity, EntityNotice::for(
            NotificationCode::EntityVerificationReminder,
            EntityNotice::FOR_ENTITY,
            ['entity' => $event->entity->name->toLocalizedText()],
            EntityLinks::signIn(),
        )->mailOnly());
    }
}

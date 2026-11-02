<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityEmailChangeRejected;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;
use App\Support\ValueObjects\EmailAddress;

final readonly class NotifyEntityEmailChangeRejected
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityEmailChangeRejected $event): void
    {
        $request = $event->request;
        $entity = $request->entity;

        if (! $entity instanceof Entity) {
            return;
        }

        $newAddress = new EmailAddress($request->new_email);
        $notice = EntityNotice::for(NotificationCode::EntityEmailChangeDecided, 'rejected', [
            'entity' => $entity->name->toLocalizedText(),
            'email' => $newAddress->masked(),
        ], EntityLinks::ownProfile());

        $this->notifier->toOwner($entity, $notice);
        $this->notifier->toAddress($newAddress, $notice, $request->user->locale);
    }
}

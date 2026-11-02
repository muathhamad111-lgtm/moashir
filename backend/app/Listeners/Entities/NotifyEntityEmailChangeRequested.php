<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\PermissionKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityEmailChangeRequested;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;
use App\Support\ValueObjects\EmailAddress;

final readonly class NotifyEntityEmailChangeRequested
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityEmailChangeRequested $event): void
    {
        $request = $event->request;
        $entity = $request->entity;

        if (! $entity instanceof Entity) {
            return;
        }

        $replacements = ['entity' => $entity->name->toLocalizedText(), 'reference' => $entity->reference()];

        $this->notifier->toPermissionHolders(
            PermissionKey::EntitiesEmailChangeApprove,
            EntityNotice::for(NotificationCode::EntityEmailChangeRequested, 'approver', $replacements, EntityLinks::emailChanges()),
            null,
        );
        $this->notifier->toAddress(
            new EmailAddress($request->old_email),
            EntityNotice::for(NotificationCode::EntityEmailChangeRequested, EntityNotice::FOR_ENTITY, [
                ...$replacements,
                'email' => new EmailAddress($request->new_email)->masked(),
            ], null),
            $request->user->locale,
        );
    }
}

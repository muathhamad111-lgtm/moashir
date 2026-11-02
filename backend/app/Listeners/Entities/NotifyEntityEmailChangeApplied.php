<?php

declare(strict_types=1);

namespace App\Listeners\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityEmailChangeApplied;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;
use App\Support\ValueObjects\EmailAddress;

final readonly class NotifyEntityEmailChangeApplied
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EntityEmailChangeApplied $event): void
    {
        $request = $event->request;
        $entity = $request->entity;

        if (! $entity instanceof Entity) {
            return;
        }

        $replacements = [
            'entity' => $entity->name->toLocalizedText(),
            'reference' => $entity->reference(),
            'email' => new EmailAddress($request->new_email)->masked(),
        ];

        if ($request->policy === EmailChangePolicy::ImmediateNotify) {
            $this->notifier->toRole(RoleKey::SystemAdmin, EntityNotice::for(NotificationCode::EntityEmailChangeRequested, 'staff_applied', $replacements, EntityLinks::record($entity->id)), $event->actorId);
        }

        $notice = EntityNotice::for(NotificationCode::EntityEmailChangeDecided, 'applied', $replacements, EntityLinks::ownProfile());

        $this->notifier->toOwner($entity, $notice);
        $this->notifier->toAddress(new EmailAddress($request->old_email), $notice, $request->user->locale);
    }
}

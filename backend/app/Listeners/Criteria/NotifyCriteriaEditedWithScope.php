<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\CriteriaEditApplied;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyCriteriaEditedWithScope
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(CriteriaEditApplied $event): void
    {
        $this->notifier->notify(CriteriaNotice::for(
            NotificationCode::CriteriaEditedWithScope,
            [
                'scope' => LocalizedText::fromTranslation('notifications.criteria.scopes.'.$event->editEvent->scope->value),
                'changes' => LocalizedText::fromChoice('quantities.changes', count($event->changes)),
            ],
            CriteriaLinks::settings(),
            [RoleKey::CriteriaOfficer, RoleKey::SystemAdmin],
        ), $event->actorId);
    }
}

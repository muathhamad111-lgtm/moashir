<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\MainCriterionWeightChanged;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;

final readonly class NotifyMainCriterionWeightChanged
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(MainCriterionWeightChanged $event): void
    {
        $this->notifier->notify(CriteriaNotice::for(
            NotificationCode::CriteriaMainWeightChanged,
            [
                'name' => $event->main->name,
                'previous' => $event->previousWeight->toDecimal(),
                'weight' => $event->main->weight->toDecimal(),
            ],
            CriteriaLinks::main($event->main->id),
            [RoleKey::CriteriaOfficer, RoleKey::SystemAdmin],
        ), $event->actorId);
    }
}

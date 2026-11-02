<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\MainCriterionCreated;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;

final readonly class NotifyMainCriterionCreated
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(MainCriterionCreated $event): void
    {
        $this->notifier->notify(CriteriaNotice::for(
            NotificationCode::CriteriaMainCreated,
            ['name' => $event->main->name],
            CriteriaLinks::main($event->main->id),
            [RoleKey::CriteriaOfficer, RoleKey::SystemAdmin],
        ), $event->actorId);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\SubCriterionCreated;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;

final readonly class NotifySubCriterionCreated
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(SubCriterionCreated $event): void
    {
        $this->notifier->notify(CriteriaNotice::for(
            NotificationCode::CriteriaSubCreated,
            ['name' => $event->sub->name],
            CriteriaLinks::sub($event->sub->main_criterion_id, $event->sub->id),
            [RoleKey::CriteriaOfficer],
        ), $event->actorId);
    }
}

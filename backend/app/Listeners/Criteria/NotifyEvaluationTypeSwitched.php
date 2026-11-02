<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\EvaluationTypeSwitched;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;

final readonly class NotifyEvaluationTypeSwitched
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(EvaluationTypeSwitched $event): void
    {
        $code = $event->sub->scoresGuidingPoints()
            ? NotificationCode::CriteriaGuidingPointsEnabled
            : NotificationCode::CriteriaDirectEvaluationEnabled;

        $this->notifier->notify(CriteriaNotice::for(
            $code,
            ['name' => $event->sub->name],
            CriteriaLinks::sub($event->sub->main_criterion_id, $event->sub->id),
            [RoleKey::CriteriaOfficer, RoleKey::SystemAdmin],
        ), $event->actorId);
    }
}

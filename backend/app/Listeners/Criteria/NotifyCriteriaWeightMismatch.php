<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\CriteriaWeightsBecameInconsistent;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;

final readonly class NotifyCriteriaWeightMismatch
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(CriteriaWeightsBecameInconsistent $event): void
    {
        $this->notifier->notify(CriteriaNotice::for(
            NotificationCode::CriteriaWeightMismatch,
            ['version' => (string) $event->version->version_number, 'count' => (string) count($event->report->problems)],
            CriteriaLinks::settings(),
            [RoleKey::CriteriaOfficer, RoleKey::SystemAdmin],
        ), $event->actorId);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\CriteriaVersionPublished;
use App\Services\Criteria\CriteriaNotifier;
use App\Support\Criteria\CriteriaLinks;

final readonly class NotifyCriteriaScaleChanged
{
    public function __construct(private CriteriaNotifier $notifier) {}

    public function handle(CriteriaVersionPublished $event): void
    {
        if (! $event->changedScale()) {
            return;
        }

        $this->notifier->notify(CriteriaNotice::for(
            NotificationCode::CriteriaScaleChanged,
            ['version' => (string) $event->version->version_number],
            CriteriaLinks::settings(),
            [RoleKey::SystemAdmin, RoleKey::CriteriaOfficer],
        ), $event->actorId);
    }
}

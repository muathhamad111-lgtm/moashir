<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CycleOpened;
use App\Models\Entities\Region;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifyCycleOpened
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CycleOpened $event): void
    {
        $cycle = $event->cycle;
        $code = NotificationCode::AdvancedCycleOpened;
        $staff = AwardNotices::for($code, AwardNotices::FOR_STAFF, $cycle, AwardLinks::cycle($cycle->id));

        $regionIds = array_values(array_map(static fn (Region $region): int => $region->id, $cycle->loadMissing('regions')->regions->all()));

        $this->notifier->toActiveEntitiesIn($regionIds, AwardNotices::for($code, AwardNotices::FOR_ENTITY, $cycle, AwardLinks::entityIntro()));
        $this->notifier->toRole(RoleKey::SystemAdmin, $staff, $event->actorId);
        $this->notifier->toRole(RoleKey::RequestFollower, $staff, $event->actorId);
    }
}

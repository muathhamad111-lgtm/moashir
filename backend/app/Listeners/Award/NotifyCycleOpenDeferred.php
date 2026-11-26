<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CycleOpenDeferred;
use App\Services\Award\CycleMembership;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifyCycleOpenDeferred
{
    public function __construct(private EntityNotifier $notifier, private CycleMembership $membership) {}

    public function handle(CycleOpenDeferred $event): void
    {
        $cycle = $event->cycle;
        $notice = AwardNotices::for(NotificationCode::CycleOpenDeferred, AwardNotices::FOR_STAFF, $cycle, AwardLinks::cycle($cycle->id), [
            'blocking' => $event->blockingCycle->name,
        ])->inAppOnly();

        $this->notifier->toRole(RoleKey::SystemAdmin, $notice, null);
        $this->notifier->toStaffUsers($this->membership->memberIdsOf($cycle->id, CycleMemberRole::AwardSecretary), $notice, null);
    }
}

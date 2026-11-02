<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\SubmissionsClosed;
use App\Services\Award\CycleMembership;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifySubmissionsClosed
{
    public function __construct(private EntityNotifier $notifier, private CycleMembership $membership) {}

    public function handle(SubmissionsClosed $event): void
    {
        $cycle = $event->cycle;
        $notice = AwardNotices::for(NotificationCode::CycleSubmissionsClosed, AwardNotices::FOR_STAFF, $cycle, AwardLinks::cycle($cycle->id))->inAppOnly();

        $this->notifier->toRole(RoleKey::SystemAdmin, $notice, $event->actorId);
        $this->notifier->toRole(RoleKey::RequestFollower, $notice, $event->actorId);
        $this->notifier->toStaffUsers($this->membership->memberIdsOf($cycle->id, CycleMemberRole::AwardSecretary), $notice, $event->actorId);
    }
}

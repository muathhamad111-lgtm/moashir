<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\EntityNotQualified;
use App\Events\Award\EntityQualified;
use App\Services\Award\CycleMembership;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\RequestNotices;
use App\Support\Award\AwardLinks;

final readonly class NotifyFilteringDecision
{
    public function __construct(private EntityNotifier $notifier, private CycleMembership $membership) {}

    public function handle(EntityQualified|EntityNotQualified $event): void
    {
        $assessment = $event->assessment;
        $cycleId = (int) $assessment->award_cycle_id;
        $link = AwardLinks::filtering($cycleId, $assessment->id);
        $code = $event instanceof EntityQualified ? NotificationCode::AdvancedQualified : NotificationCode::AdvancedNotQualified;
        $extra = ['percent' => $event->decision->initial_percent->toDisplay(), 'threshold' => $event->decision->threshold_percent->toDisplay()];

        $this->notifier->toStaffUsers(
            $this->membership->memberIdsOf($cycleId, CycleMemberRole::FilteringOfficer),
            RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, $link, $extra),
            $event->actorId,
        );

        if ($event->decision->is_override) {
            $exception = RequestNotices::for(NotificationCode::AdvancedFilteringException, RequestNotices::FOR_STAFF, $assessment, $link, $extra);
            $this->notifier->toStaffUsers($this->membership->memberIdsOf($cycleId, CycleMemberRole::AwardSecretary), $exception, $event->actorId);
            $this->notifier->toRole(RoleKey::SystemAdmin, $exception, $event->actorId);
        }
    }
}

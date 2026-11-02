<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\InitialAssessmentCompleted;
use App\Services\Award\CycleMembership;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\Award\AwardLinks;

final readonly class NotifyInitialAssessmentCompleted
{
    public function __construct(private EntityNotifier $notifier, private CycleMembership $membership) {}

    public function handle(InitialAssessmentCompleted $event): void
    {
        $assessment = $event->assessment;
        $cycleId = (int) $assessment->award_cycle_id;
        $code = NotificationCode::AdvancedInitialAssessmentCompleted;
        $extra = ['percent' => $event->snapshot->percent->toDisplay()];

        $this->notifier->toStaffUsers(
            $this->membership->memberIdsOf($cycleId, CycleMemberRole::FilteringOfficer),
            RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, AwardLinks::filtering($cycleId, $assessment->id), $extra)->inAppOnly(),
            $event->actorId,
        );
        $this->notifier->toRole(
            RoleKey::RequestFollower,
            RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, AssessmentLinks::request($assessment->id), $extra)->inAppOnly(),
            $event->actorId,
        );
    }
}

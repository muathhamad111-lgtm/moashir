<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\RequestAssigned;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyRequestAssigned
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(RequestAssigned $event): void
    {
        $assessment = $event->assessment;

        if (! $assessment->isAccredited()) {
            return;
        }

        $code = NotificationCode::AccreditedAssigned;

        $this->notifier->toStaffUser($event->assessorId, RequestNotices::for($code, RequestNotices::FOR_ASSESSOR, $assessment, AssessmentLinks::review($assessment->id)));
        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, AssessmentLinks::request($assessment->id)), $event->actorId);

        if ($event->previousAssessorId !== null) {
            $this->notifier->toStaffUser($event->previousAssessorId, RequestNotices::variant(
                $code,
                $code->value.'.released',
                $assessment,
                AssessmentLinks::request($assessment->id),
            )->inAppOnly());
        }
    }
}

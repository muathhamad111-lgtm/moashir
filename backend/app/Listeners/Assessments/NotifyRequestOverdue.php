<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\RequestOverdue;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyRequestOverdue
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(RequestOverdue $event): void
    {
        $assessment = $event->assessment;
        $code = NotificationCode::AccreditedReviewOverdue;
        $staffNotice = RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, AssessmentLinks::request($assessment->id));

        if ($event->assessorId !== null) {
            $this->notifier->toStaffUser($event->assessorId, RequestNotices::for($code, RequestNotices::FOR_ASSESSOR, $assessment, AssessmentLinks::review($assessment->id)));
        }

        $this->notifier->toRole(RoleKey::RequestFollower, $staffNotice, $event->assessorId);
        $this->notifier->toRole(RoleKey::SystemAdmin, $staffNotice, $event->assessorId);
    }
}

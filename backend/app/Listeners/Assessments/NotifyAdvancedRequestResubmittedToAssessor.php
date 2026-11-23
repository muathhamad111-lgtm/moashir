<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AdvancedRequestResubmittedToAssessor;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAdvancedRequestResubmittedToAssessor
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AdvancedRequestResubmittedToAssessor $event): void
    {
        $assessment = $event->assessment;

        $this->notifier->toStaffUser($event->assessorId, RequestNotices::variant(
            NotificationCode::AdvancedAssigned,
            NotificationCode::AdvancedAssigned->value.'.resubmitted',
            $assessment,
            AssessmentLinks::review($assessment->id),
        )->inAppOnly());
    }
}

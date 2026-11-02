<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AccreditedRequestResubmitted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAccreditedRequestResubmitted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AccreditedRequestResubmitted $event): void
    {
        $assessment = $event->assessment;

        $this->notifier->toStaffUser($event->assessorId, RequestNotices::variant(
            NotificationCode::AccreditedReviewStarted,
            NotificationCode::AccreditedReviewStarted->value.'.resubmitted',
            $assessment,
            AssessmentLinks::review($assessment->id),
        )->inAppOnly());
    }
}

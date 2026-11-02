<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AssessmentCompleted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAssessmentCompleted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AssessmentCompleted $event): void
    {
        $assessment = $event->assessment;
        $percent = ['percent' => $event->snapshot->percent->toDisplay()];

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedCompleted,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedResult($assessment->id),
            $percent,
        ));
        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedFeedbackAvailable,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedFeedback($assessment->id),
        ));
        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::for(
            NotificationCode::AccreditedCompleted,
            RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::request($assessment->id),
            $percent,
        ), null);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\ReviewStarted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyReviewStarted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(ReviewStarted $event): void
    {
        $assessment = $event->assessment;

        if (! $assessment->isAccredited()) {
            return;
        }

        $code = NotificationCode::AccreditedReviewStarted;

        $this->notifier->toOwner($assessment->entity, RequestNotices::for($code, RequestNotices::FOR_ENTITY, $assessment, AssessmentLinks::accreditedForm())->inAppOnly());
        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, AssessmentLinks::request($assessment->id))->inAppOnly(), $event->actorId);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AccreditedRequestSubmitted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAccreditedRequestSubmitted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AccreditedRequestSubmitted $event): void
    {
        $assessment = $event->assessment;
        $link = AssessmentLinks::request($assessment->id);

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedSubmitted,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedForm(),
        ));
        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::for(
            NotificationCode::AccreditedSubmitted,
            RequestNotices::FOR_STAFF,
            $assessment,
            $link,
        )->mailOnly(), null);
        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::for(
            NotificationCode::AccreditedAwaitingAssignment,
            RequestNotices::FOR_STAFF,
            $assessment,
            $link,
        )->inAppOnly(), null);
    }
}

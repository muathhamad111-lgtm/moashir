<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AdvancedRequestResubmitted;
use App\Events\Assessments\AdvancedRequestSubmitted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAdvancedRequestSubmitted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AdvancedRequestSubmitted|AdvancedRequestResubmitted $event): void
    {
        $assessment = $event->assessment;
        $code = NotificationCode::AdvancedSubmitted;
        $link = AssessmentLinks::request($assessment->id);

        if ($event instanceof AdvancedRequestResubmitted) {
            $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::variant($code, $code->value.'.resubmitted', $assessment, $link)->inAppOnly(), $event->actorId);

            return;
        }

        $this->notifier->toOwner($assessment->entity, RequestNotices::for($code, RequestNotices::FOR_ENTITY, $assessment, AssessmentLinks::advancedForm()));
        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, $link), $event->actorId);
    }
}

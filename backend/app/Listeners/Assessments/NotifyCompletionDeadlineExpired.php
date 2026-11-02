<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\CompletionDeadlineExpired;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyCompletionDeadlineExpired
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CompletionDeadlineExpired $event): void
    {
        $assessment = $event->assessment;
        $outcome = $event->completionRequest->expiry_outcome;
        $code = $assessment->isAdvanced() ? NotificationCode::AdvancedCompletionExpired : NotificationCode::AccreditedCompletionExpired;
        $variant = $code->value.'.'.$outcome->noticeKey().'.';

        $this->notifier->toOwner($assessment->entity, RequestNotices::variant($code, $variant.RequestNotices::FOR_ENTITY, $assessment, AssessmentLinks::entityFormOf($assessment)));

        if ($event->assessorId !== null) {
            $link = $outcome->endsAssignment() ? AssessmentLinks::request($assessment->id) : AssessmentLinks::review($assessment->id);
            $this->notifier->toStaffUser($event->assessorId, RequestNotices::variant($code, $variant.RequestNotices::FOR_ASSESSOR, $assessment, $link));
        }

        $this->notifier->toRole(RoleKey::RequestFollower, RequestNotices::variant(
            $code,
            $variant.RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::request($assessment->id),
        ), $event->assessorId);
    }
}

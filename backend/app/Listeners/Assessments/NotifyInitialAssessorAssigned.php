<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\RequestAssigned;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyInitialAssessorAssigned
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(RequestAssigned $event): void
    {
        $assessment = $event->assessment;

        if (! $assessment->isAdvanced()) {
            return;
        }

        $code = NotificationCode::AdvancedAssigned;
        $this->notifier->toStaffUser($event->assessorId, RequestNotices::for($code, RequestNotices::FOR_ASSESSOR, $assessment, AssessmentLinks::review($assessment->id)));

        if ($event->previousAssessorId !== null) {
            $this->notifier->toStaffUser($event->previousAssessorId, RequestNotices::variant($code, $code->value.'.released', $assessment, AssessmentLinks::request($assessment->id))->inAppOnly());
        }
    }
}

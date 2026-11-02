<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\OpenAssessmentReset;
use App\Models\Assessments\AssessmentAssignment;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAssessorOfAssessmentReset
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(OpenAssessmentReset $event): void
    {
        $assessment = $event->assessment;
        $assignment = $assessment->loadMissing('activeAssignment')->activeAssignment;

        if (! $assessment->isAccredited() || ! $assignment instanceof AssessmentAssignment) {
            return;
        }

        $this->notifier->toStaffUser($assignment->assessor_user_id, RequestNotices::for(
            NotificationCode::CriteriaEditedWithScope,
            RequestNotices::FOR_ASSESSOR,
            $assessment,
            AssessmentLinks::review($assessment->id),
        ));
    }
}

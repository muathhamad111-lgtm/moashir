<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\OpenAssessmentReset;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\AssessmentNotices;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyEntityOfAssessmentReset
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(OpenAssessmentReset $event): void
    {
        $assessment = $event->assessment;
        $code = NotificationCode::CriteriaEditedWithScope;

        $notice = $assessment->isAccredited()
            ? RequestNotices::for($code, RequestNotices::FOR_ENTITY, $assessment, AssessmentLinks::accreditedForm())
            : AssessmentNotices::for($code, ['number' => $assessment->request_number], AssessmentLinks::selfForm())->forPath($assessment->path);

        $this->notifier->toOwner($assessment->entity, $notice);
    }
}

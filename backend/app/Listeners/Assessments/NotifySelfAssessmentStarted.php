<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\SelfAssessmentStarted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\AssessmentNotices;

final readonly class NotifySelfAssessmentStarted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(SelfAssessmentStarted $event): void
    {
        $assessment = $event->assessment;

        $this->notifier->toOwner($assessment->entity, AssessmentNotices::for(
            NotificationCode::SelfAssessmentStarted,
            ['number' => $assessment->request_number],
            AssessmentLinks::selfForm(),
        )->forPath($assessment->path)->inAppOnly());
    }
}

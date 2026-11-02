<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\SelfAssessmentSubmitted;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\AssessmentNotices;

final readonly class NotifySelfAssessmentResultAvailable
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(SelfAssessmentSubmitted $event): void
    {
        $assessment = $event->assessment;

        $this->notifier->toOwner($assessment->entity, AssessmentNotices::for(
            NotificationCode::SelfAssessmentResultAvailable,
            ['number' => $assessment->request_number, 'percent' => $event->result->percent->toDisplay()],
            AssessmentLinks::selfResult($assessment->id),
        )->inAppOnly());
    }
}

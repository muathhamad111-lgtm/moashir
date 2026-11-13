<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\SelfAssessmentDraftSaved;
use App\Services\Assessments\DraftNoticeThrottle;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\AssessmentNotices;

final readonly class NotifySelfAssessmentDraftSaved
{
    public function __construct(private EntityNotifier $notifier, private DraftNoticeThrottle $throttle) {}

    public function handle(SelfAssessmentDraftSaved $event): void
    {
        $assessment = $event->assessment;

        if ($event->changeCount === 0 || ! $this->throttle->allows($assessment)) {
            return;
        }

        $this->notifier->toOwner($assessment->entity, AssessmentNotices::for(
            NotificationCode::SelfAssessmentDraftSaved,
            ['number' => $assessment->request_number],
            AssessmentLinks::selfForm(),
        )->forPath($assessment->path)->inAppOnly());
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AccreditedDraftSaved;
use App\Services\Assessments\DraftNoticeThrottle;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAccreditedDraftSaved
{
    public function __construct(private EntityNotifier $notifier, private DraftNoticeThrottle $throttle) {}

    public function handle(AccreditedDraftSaved $event): void
    {
        $assessment = $event->assessment;

        if (! $assessment->isAccredited() || ! $this->throttle->allows($assessment)) {
            return;
        }

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedDraftSaved,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedForm(),
        )->inAppOnly());
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AccreditedRequestOpened;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAccreditedRequestOpened
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AccreditedRequestOpened $event): void
    {
        $assessment = $event->assessment;

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedFormOpened,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedForm(),
        ));
    }
}

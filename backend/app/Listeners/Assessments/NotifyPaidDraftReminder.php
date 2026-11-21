<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\PaidDraftReminded;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyPaidDraftReminder
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(PaidDraftReminded $event): void
    {
        $assessment = $event->assessment;

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedPaidDraftReminder,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedRequestTab($assessment->id),
        ));
    }
}

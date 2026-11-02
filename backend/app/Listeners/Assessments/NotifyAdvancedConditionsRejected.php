<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AdvancedConditionsRejected;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAdvancedConditionsRejected
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AdvancedConditionsRejected $event): void
    {
        $this->notifier->toOwner($event->assessment->entity, RequestNotices::for(
            NotificationCode::AdvancedConditionsRejected,
            RequestNotices::FOR_ENTITY,
            $event->assessment,
            AssessmentLinks::advancedForm(),
        )->inAppOnly());
    }
}

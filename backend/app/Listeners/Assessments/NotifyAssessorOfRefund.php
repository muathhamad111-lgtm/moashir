<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AccreditedRequestRefunded;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAssessorOfRefund
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AccreditedRequestRefunded $event): void
    {
        if ($event->assessorId === null) {
            return;
        }

        $code = NotificationCode::AccreditedAssigned;

        $this->notifier->toStaffUser($event->assessorId, RequestNotices::variant(
            $code,
            $code->value.'.refunded',
            $event->assessment,
            AssessmentLinks::request($event->assessment->id),
        )->inAppOnly());
    }
}

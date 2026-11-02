<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AdvancedRequestWithdrawn;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyAdvancedRequestWithdrawn
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AdvancedRequestWithdrawn $event): void
    {
        $assessment = $event->assessment;
        $code = NotificationCode::AdvancedWithdrawn;
        $staff = RequestNotices::for($code, RequestNotices::FOR_STAFF, $assessment, AssessmentLinks::request($assessment->id));

        $this->notifier->toOwner($assessment->entity, RequestNotices::for($code, RequestNotices::FOR_ENTITY, $assessment, AssessmentLinks::advancedForm()));
        $this->notifier->toRole(RoleKey::RequestFollower, $staff, $event->actorId);

        if ($event->assessorId !== null) {
            $this->notifier->toStaffUser($event->assessorId, $staff);
        }
    }
}

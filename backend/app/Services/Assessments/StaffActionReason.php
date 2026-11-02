<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;

final readonly class StaffActionReason
{
    public function __construct(private RequestContext $context, private SecurityAlerter $alerter) {}

    public function record(User $actor, ?string $reason): void
    {
        $reason = $reason === null ? null : trim($reason);

        if ($actor->isSuperAdmin() && ($reason === null || $reason === '')) {
            throw AssessmentRejected::reasonRequired();
        }

        $this->context->recordReason($reason === '' ? null : $reason);

        if ($actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }
}

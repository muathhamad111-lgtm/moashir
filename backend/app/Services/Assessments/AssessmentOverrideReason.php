<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;

final readonly class AssessmentOverrideReason
{
    public function __construct(private RequestContext $context, private SecurityAlerter $alerter) {}

    public function requireFor(User $actor, Assessment $assessment, ?string $reason): void
    {
        if ($assessment->isOwnedBy($actor->entity_id)) {
            return;
        }

        if ($reason === null || trim($reason) === '') {
            throw AssessmentRejected::reasonRequired();
        }

        $this->context->recordReason($reason);
        $this->alerter->raise(NotificationCode::ReasonRequiredAction);
    }
}

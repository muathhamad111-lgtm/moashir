<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Enums\Audit\AuditEvent;

enum CompletionExpiryOutcome: string
{
    case ContinueAsSubmitted = 'continue_as_submitted';
    case Decline = 'decline';

    public function requestStatus(): CompletionRequestStatus
    {
        return match ($this) {
            self::ContinueAsSubmitted => CompletionRequestStatus::ExpiredContinued,
            self::Decline => CompletionRequestStatus::ExpiredDeclined,
        };
    }

    public function assessmentStatusOn(AssessmentPath $path): ReviewedAssessmentState
    {
        return $path->reviewStages()->afterCompletionExpiry($this);
    }

    public function auditEvent(): AuditEvent
    {
        return match ($this) {
            self::ContinueAsSubmitted => AuditEvent::AssessmentCompletionExpired,
            self::Decline => AuditEvent::AssessmentDeclinedIncomplete,
        };
    }

    public function endsAssignment(): bool
    {
        return $this === self::Decline;
    }

    public function noticeKey(): string
    {
        return match ($this) {
            self::ContinueAsSubmitted => 'continued',
            self::Decline => 'declined',
        };
    }
}

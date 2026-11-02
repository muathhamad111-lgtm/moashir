<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum CompletionRequestStatus: string
{
    case Open = 'open';
    case Resubmitted = 'resubmitted';
    case Cancelled = 'cancelled';
    case ExpiredContinued = 'expired_continued';
    case ExpiredDeclined = 'expired_declined';
}

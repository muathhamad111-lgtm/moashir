<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum EntityPeriodState: string
{
    case Assessed = 'assessed';
    case InProgress = 'in_progress';
    case NotSubmitted = 'not_submitted';
    case Missed = 'missed';
    case ClosedWithoutResult = 'closed_without_result';
    case Frozen = 'frozen';
    case NotRequired = 'not_required';

    public function invitesSubmission(): bool
    {
        return $this === self::NotSubmitted || $this === self::ClosedWithoutResult;
    }
}

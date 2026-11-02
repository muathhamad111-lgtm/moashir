<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum AssignmentEndReason: string
{
    case Refunded = 'refunded';
    case Reopened = 'reopened';
    case ForcedTransition = 'forced_transition';
    case DeclinedIncomplete = 'declined_incomplete';
    case Withdrawn = 'withdrawn';
}

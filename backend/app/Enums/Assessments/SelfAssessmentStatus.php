<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\AssessmentState;

enum SelfAssessmentStatus: string implements AssessmentState
{
    case AwaitingPayment = 'awaiting_payment';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ResultAvailable = 'result_available';
    case Refunded = 'refunded';

    public function isOpen(): bool
    {
        return $this === self::Draft;
    }

    public function exposesResult(): bool
    {
        return $this === self::ResultAvailable;
    }

    public function allowsTransitionTo(AssessmentState $next): bool
    {
        return match ($this) {
            self::AwaitingPayment => $next === self::Draft,
            self::Draft => in_array($next, [self::Draft, self::Submitted, self::Refunded], true),
            self::Submitted => $next === self::ResultAvailable,
            self::ResultAvailable, self::Refunded => false,
        };
    }
}

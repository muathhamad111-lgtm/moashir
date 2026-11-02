<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\AssessmentState;

enum SelfAssessmentStatus: string implements AssessmentState
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ResultAvailable = 'result_available';

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
            self::Draft => $next === self::Draft || $next === self::Submitted,
            self::Submitted => $next === self::ResultAvailable,
            self::ResultAvailable => false,
        };
    }
}

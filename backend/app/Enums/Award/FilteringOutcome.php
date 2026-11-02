<?php

declare(strict_types=1);

namespace App\Enums\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;

enum FilteringOutcome: string
{
    case Qualified = 'qualified';
    case NotQualified = 'not_qualified';

    public function assessmentStatus(): AdvancedAssessmentStatus
    {
        return match ($this) {
            self::Qualified => AdvancedAssessmentStatus::QualifiedForCommittee,
            self::NotQualified => AdvancedAssessmentStatus::NotQualified,
        };
    }
}

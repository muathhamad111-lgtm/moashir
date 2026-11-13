<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentPeriod;

final readonly class MissedPeriodRow
{
    public function __construct(public AssessmentPeriod $period) {}
}

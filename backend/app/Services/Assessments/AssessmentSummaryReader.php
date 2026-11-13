<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentSummary;

final class AssessmentSummaryReader
{
    public function currentOf(Assessment $assessment): ?AssessmentSummary
    {
        if (! $assessment->isAccredited()) {
            return null;
        }

        return AssessmentSummary::query()->where('assessment_id', $assessment->id)->current()->first();
    }
}

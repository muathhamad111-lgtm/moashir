<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;

final class AssessmentRowLock
{
    public function lock(int $assessmentId): Assessment
    {
        return Assessment::query()
            ->with(['entity', 'criteriaVersion', 'currentResult'])
            ->whereKey($assessmentId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

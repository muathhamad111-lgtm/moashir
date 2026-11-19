<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Entities\Entity;

final class LastAccreditedAssessmentPresenter
{
    /**
     * @return array{percent: string|null, completed_at: string|null}|null
     */
    public static function of(Entity $entity): ?array
    {
        if (! $entity->relationLoaded('lastAccreditedAssessment')) {
            return null;
        }

        $assessment = $entity->lastAccreditedAssessment;

        if (! $assessment instanceof Assessment) {
            return null;
        }

        $result = $assessment->relationLoaded('currentResult') ? $assessment->currentResult : null;

        return [
            'percent' => $result instanceof AssessmentResultSnapshot ? $result->percent->toDisplay() : null,
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Entities\EntityErasureStep;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;

final class SelfAssessmentEraser implements EntityErasureStep
{
    public function erase(Entity $entity): void
    {
        Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::SelfAssessment)->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;

final readonly class ReboundAssessment
{
    public function __construct(public Assessment $assessment, public RebindPlan $plan) {}
}

<?php

declare(strict_types=1);

namespace App\Contracts\Assessments;

use App\Models\Assessments\Assessment;

interface AssessmentReopenStep
{
    public function onReopen(Assessment $assessment, string $reason): void;
}

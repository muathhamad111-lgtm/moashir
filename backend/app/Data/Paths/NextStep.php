<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\NextStepKey;

final readonly class NextStep
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public NextStepKey $key, public AssessmentPath $path, public NextStepTarget $target, public array $context) {}
}

<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;
use Carbon\CarbonImmutable;

final readonly class PreparingRequest
{
    public function __construct(public Assessment $assessment, public ?SubmissionProgress $progress, public ?CarbonImmutable $currentPeriodEndsOn = null) {}
}

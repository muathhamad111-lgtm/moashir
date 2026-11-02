<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentScore;

final readonly class CarriedScore
{
    public function __construct(public AssessmentScore $previous, public ScorableUnit $target) {}
}

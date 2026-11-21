<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentScore;

final readonly class RoundScore
{
    public function __construct(public int $round, public AssessmentScore $score) {}
}

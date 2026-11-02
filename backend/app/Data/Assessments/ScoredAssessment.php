<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ScoredAssessment
{
    public function __construct(public ScoringTree $tree, public ScoreSheet $sheet) {}
}

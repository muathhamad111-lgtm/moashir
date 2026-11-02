<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;

final readonly class AssessmentScores
{
    public function __construct(public Assessment $assessment, public ScoringTree $tree, public ScoreSheet $sheet) {}

    public function scoredCount(): int
    {
        return count($this->tree->scorableUnits()) - count($this->tree->unscoredUnits($this->sheet));
    }

    public function requiredCount(): int
    {
        return count($this->tree->scorableUnits());
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Models\Award\CommitteeAssignment;

final readonly class CommitteeScores
{
    public function __construct(public CommitteeAssignment $assignment, public ScoringTree $tree, public ScoreSheet $sheet) {}

    public function scoredCount(): int
    {
        return count($this->tree->scorableUnits()) - count($this->tree->unscoredUnits($this->sheet));
    }

    public function requiredCount(): int
    {
        return count($this->tree->scorableUnits());
    }
}

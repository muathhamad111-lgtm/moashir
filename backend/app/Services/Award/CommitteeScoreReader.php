<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Enums\Criteria\ScaleKind;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeScore;
use Illuminate\Database\Eloquent\Collection;

final class CommitteeScoreReader
{
    /**
     * @return Collection<int, CommitteeScore>
     */
    public function currentRows(CommitteeAssignment $assignment): Collection
    {
        return CommitteeScore::query()->where('committee_assignment_id', $assignment->id)->current()->orderBy('id')->get();
    }

    public function read(CommitteeAssignment $assignment, ScoringTree $tree, ScaleKind $scale): ScoreSheet
    {
        $entries = [];

        foreach ($this->currentRows($assignment) as $row) {
            $unit = $tree->unit($row->level, $row->nodeId());

            if ($unit instanceof ScorableUnit) {
                $entries[] = new ScoredUnit($unit, $row->rawScore($scale));
            }
        }

        return ScoreSheet::of($entries);
    }
}

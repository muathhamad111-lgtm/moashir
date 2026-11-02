<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentScore;
use Illuminate\Database\Eloquent\Collection;

final class ScoreSheetReader
{
    /**
     * @return Collection<int, AssessmentScore>
     */
    public function currentRows(Assessment $assessment): Collection
    {
        return AssessmentScore::query()
            ->where('assessment_id', $assessment->id)
            ->current()
            ->orderBy('id')
            ->get();
    }

    public function read(Assessment $assessment, ScoringTree $tree): ScoreSheet
    {
        $entries = [];

        foreach ($this->currentRows($assessment) as $row) {
            $unit = $tree->unit($row->level, $row->nodeId());

            if ($unit instanceof ScorableUnit) {
                $entries[] = new ScoredUnit($unit, $row->rawScore($assessment->scale_kind));
            }
        }

        return ScoreSheet::of($entries);
    }
}

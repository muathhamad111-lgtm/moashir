<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CarriedScore;
use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\RebindPlan;
use App\Data\Assessments\ScorableUnit;
use App\Models\Assessments\AssessmentScore;

final class CriteriaRebindPlanner
{
    /**
     * @param  iterable<AssessmentScore>  $currentRows
     */
    public function plan(iterable $currentRows, CriteriaRebind $rebind): RebindPlan
    {
        $carried = [];
        $cleared = [];

        foreach ($currentRows as $row) {
            $target = $rebind->effect->keepsScoresOfUnchangedItems() ? $this->targetFor($row, $rebind) : null;

            if ($target instanceof ScorableUnit) {
                $carried[] = new CarriedScore($row, $target);
            } else {
                $cleared[] = $row;
            }
        }

        return new RebindPlan($carried, $cleared);
    }

    private function targetFor(AssessmentScore $row, CriteriaRebind $rebind): ?ScorableUnit
    {
        $code = $rebind->from->unit($row->level, $row->nodeId())?->code;

        if ($code === null || $rebind->isRescored($code)) {
            return null;
        }

        $target = $rebind->to->unitWithCode($code);

        return $target?->level === $row->level ? $target : null;
    }
}

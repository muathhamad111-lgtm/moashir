<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\RebindCause;
use App\Data\Assessments\RebindPlan;
use App\Data\Assessments\ReboundAssessment;
use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoringTree;
use App\Models\Assessments\AssessmentCriteriaMove;
use App\Models\Assessments\AssessmentScore;
use Closure;

final class CriteriaMoveRecorder
{
    public function record(ReboundAssessment $rebound, CriteriaRebind $rebind, RebindCause $cause): void
    {
        AssessmentCriteriaMove::query()->create([
            'assessment_id' => $rebound->assessment->id,
            'from_version_id' => $rebind->from->versionId,
            'to_version_id' => $rebind->to->versionId,
            'scope' => $rebind->effect->scope,
            'triggered_by' => $cause->trigger,
            'kept' => $this->kept($rebind, $rebound->plan),
            'reset' => $this->reset($rebind, $rebound->plan),
            'added' => $this->added($rebind),
        ]);
    }

    /**
     * @return list<array{level: string, code: string}>
     */
    private function kept(CriteriaRebind $rebind, RebindPlan $plan): array
    {
        $codes = $plan->carriedCodes();

        return $this->unitsOf($rebind->to, static fn (ScorableUnit $unit): bool => in_array($unit->code, $codes, true));
    }

    /**
     * @return list<array{level: string, code: string}>
     */
    private function reset(CriteriaRebind $rebind, RebindPlan $plan): array
    {
        $keys = array_map(static fn (AssessmentScore $row): string => ScorableUnit::keyFor($row->level, $row->nodeId()), $plan->cleared);

        return $this->unitsOf($rebind->from, static fn (ScorableUnit $unit): bool => in_array($unit->key(), $keys, true));
    }

    /**
     * @return list<array{level: string, code: string}>
     */
    private function added(CriteriaRebind $rebind): array
    {
        $previousCodes = array_map(static fn (ScorableUnit $unit): string => $unit->code, $rebind->from->scorableUnits());

        return $this->unitsOf($rebind->to, static fn (ScorableUnit $unit): bool => ! in_array($unit->code, $previousCodes, true));
    }

    /**
     * @param  Closure(ScorableUnit): bool  $selects
     * @return list<array{level: string, code: string}>
     */
    private function unitsOf(ScoringTree $tree, Closure $selects): array
    {
        return array_values(array_map(
            static fn (ScorableUnit $unit): array => ['level' => $unit->level->value, 'code' => $unit->code],
            array_filter($tree->scorableUnits(), $selects),
        ));
    }
}

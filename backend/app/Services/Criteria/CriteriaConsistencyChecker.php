<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\ConsistencyProblem;
use App\Data\Criteria\ConsistencyReport;
use App\Enums\Criteria\ConsistencyProblemCode;
use App\Enums\Criteria\CriteriaNodeType;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\Criteria\ActiveWeights;

final class CriteriaConsistencyChecker
{
    public function check(CriteriaVersion $version): ConsistencyReport
    {
        $activeMains = $version->mainCriteria->filter(static fn (MainCriterion $main): bool => $main->is_active);

        if ($activeMains->isEmpty()) {
            return new ConsistencyReport([new ConsistencyProblem(CriteriaNodeType::Main, null, null, ConsistencyProblemCode::NoActiveMain)]);
        }

        $problems = [];

        foreach ($activeMains as $main) {
            array_push($problems, ...$this->problemsOf($main));
        }

        return new ConsistencyReport($problems);
    }

    /**
     * @return list<ConsistencyProblem>
     */
    private function problemsOf(MainCriterion $main): array
    {
        $problems = [];
        $allocated = ActiveWeights::total($main->subCriteria);

        if (! $allocated->equals($main->weight)) {
            $problems[] = new ConsistencyProblem(CriteriaNodeType::Main, $main->id, $main->code, ConsistencyProblemCode::WeightsMismatch, $main->weight, $allocated);
        }

        foreach ($main->subCriteria as $sub) {
            $problem = $sub->is_active ? $this->problemOf($sub) : null;

            if ($problem instanceof ConsistencyProblem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    private function problemOf(SubCriterion $sub): ?ConsistencyProblem
    {
        if (! $sub->scoresGuidingPoints()) {
            return null;
        }

        $hasActivePoint = $sub->guidingPoints->contains(static fn (GuidingPoint $point): bool => $point->is_active);

        if (! $hasActivePoint) {
            return new ConsistencyProblem(CriteriaNodeType::Sub, $sub->id, $sub->code, ConsistencyProblemCode::NoActivePoint);
        }

        $allocated = ActiveWeights::total($sub->guidingPoints);

        return $allocated->isGreaterThan($sub->weight)
            ? new ConsistencyProblem(CriteriaNodeType::Sub, $sub->id, $sub->code, ConsistencyProblemCode::PointsExceedSub, $sub->weight, $allocated)
            : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AssessmentResult;
use App\Data\Assessments\MainResult;
use App\Data\Assessments\PointResult;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringMain;
use App\Data\Assessments\ScoringPoint;
use App\Data\Assessments\ScoringSub;
use App\Data\Assessments\ScoringTree;
use App\Data\Assessments\SubResult;
use App\Support\FixedPoint;
use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;

final class ScoringEngine
{
    public function compute(ScoringTree $tree, ScoreSheet $scores): AssessmentResult
    {
        $mains = array_map(fn (ScoringMain $main): MainResult => $this->main($main, $scores), $tree->mains);
        $total = EarnedScore::sum(array_map(static fn (MainResult $main): EarnedScore => $main->earned, $mains));
        $totalWeight = $tree->totalWeight();

        return new AssessmentResult($total, $totalWeight, $total->percentOf($totalWeight), $mains);
    }

    private function main(ScoringMain $main, ScoreSheet $scores): MainResult
    {
        $subs = array_map(fn (ScoringSub $sub): SubResult => $this->sub($sub, $scores), $main->subs);
        $earned = EarnedScore::sum(array_map(static fn (SubResult $sub): EarnedScore => $sub->earned, $subs));

        return new MainResult($main, $earned->percentOf($main->weight), $earned, $subs);
    }

    private function sub(ScoringSub $sub, ScoreSheet $scores): SubResult
    {
        if (! $sub->scoresPoints()) {
            $raw = $scores->rawFor($sub->unit());
            $percent = $raw?->percent() ?? Percent::zero();

            return new SubResult($sub, $raw, $percent, EarnedScore::ofPercentOfWeight($percent, $sub->weight));
        }

        $points = array_map(fn (ScoringPoint $point): PointResult => $this->point($point, $scores), $sub->points);
        $earned = $this->normalizedEarned($sub, $points);

        return new SubResult($sub, null, $earned->percentOf($sub->weight), $earned, $points);
    }

    private function point(ScoringPoint $point, ScoreSheet $scores): PointResult
    {
        $raw = $scores->rawFor($point->unit());
        $percent = $raw?->percent() ?? Percent::zero();

        return new PointResult($point, $raw, $percent, EarnedScore::ofPercentOfWeight($percent, $point->weight));
    }

    /**
     * @param  list<PointResult>  $points
     */
    private function normalizedEarned(ScoringSub $sub, array $points): EarnedScore
    {
        $weightedPercents = 0;
        $pointWeights = 0;

        foreach ($points as $point) {
            $weight = max(0, $point->point->weight->hundredths);
            $weightedPercents += $point->percent->tenThousandths * $weight;
            $pointWeights += $weight;
        }

        if ($pointWeights === 0 || ! $sub->weight->isPositive()) {
            return EarnedScore::zero();
        }

        return EarnedScore::fromHundredMillionths(
            FixedPoint::multiplyDivideRoundingHalfUp($weightedPercents, $sub->weight->hundredths, $pointWeights),
        );
    }
}

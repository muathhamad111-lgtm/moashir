<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Assessments\ResultSnapshotRecorder;
use App\Services\Assessments\ScoringEngine;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Support\FixedPoint;
use App\Support\ValueObjects\RawScore;

final readonly class DemoResultScorer
{
    private const int SPREAD = 8;

    public function __construct(
        private ScoringTreeBuilder $trees,
        private ScoringEngine $engine,
        private ResultSnapshotRecorder $snapshots,
    ) {}

    /**
     * @param  list<int>  $profile
     */
    public function score(Assessment $assessment, CriteriaVersion $version, array $profile, ResultSnapshotKind $kind, ?int $computedBy): void
    {
        $tree = $this->trees->build($version);
        $this->snapshots->record($assessment, $this->engine->compute($tree, $this->sheetOf($tree, $assessment, $profile)), $kind, $computedBy);
    }

    /**
     * @return list<string>
     */
    public function subCodesOf(CriteriaVersion $version): array
    {
        $codes = [];

        foreach ($this->trees->build($version)->mains as $main) {
            foreach ($main->subs as $sub) {
                $codes[] = $sub->code;
            }
        }

        return $codes;
    }

    /**
     * @param  list<int>  $profile
     */
    private function sheetOf(ScoringTree $tree, Assessment $assessment, array $profile): ScoreSheet
    {
        $units = [];

        foreach ($tree->mains as $mainIndex => $main) {
            foreach ($main->subs as $subIndex => $sub) {
                $percent = max(0, min(100, $profile[$mainIndex % count($profile)] + (($subIndex % 3) - 1) * self::SPREAD));

                foreach ($sub->scorableUnits() as $unit) {
                    $units[] = new ScoredUnit($unit, RawScore::on($assessment->scale_kind, FixedPoint::format(intdiv($percent * $assessment->scale_kind->maximumHundredths(), 100), 2)));
                }
            }
        }

        return ScoreSheet::of($units);
    }
}

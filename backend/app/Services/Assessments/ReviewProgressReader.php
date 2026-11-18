<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ReviewProgress;
use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoringTree;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentScore;

/**
 * Counts, for a page of requests, how many scorable units carry a current score: the same
 * measure as the review screen's progress, read with one score query for the whole page.
 */
final readonly class ReviewProgressReader
{
    public function __construct(private ScoringTreeBuilder $trees) {}

    /**
     * @param  list<Assessment>  $assessments
     * @return array<int, ReviewProgress>
     */
    public function readMany(array $assessments): array
    {
        $scoredKeys = $this->scoredKeysByAssessment($assessments);
        $treesByVersion = [];
        $progress = [];

        foreach ($assessments as $assessment) {
            $tree = $treesByVersion[$assessment->criteria_version_id] ??= $this->trees->build($assessment->loadMissing('criteriaVersion')->criteriaVersion);
            $progress[$assessment->id] = $this->progressOf($tree, $scoredKeys[$assessment->id] ?? []);
        }

        return $progress;
    }

    /**
     * @param  array<string, true>  $scoredKeys
     */
    private function progressOf(ScoringTree $tree, array $scoredKeys): ReviewProgress
    {
        $units = $tree->scorableUnits();
        $scored = 0;

        foreach ($units as $unit) {
            $scored += isset($scoredKeys[$unit->key()]) ? 1 : 0;
        }

        return new ReviewProgress($scored, count($units));
    }

    /**
     * @param  list<Assessment>  $assessments
     * @return array<int, array<string, true>>
     */
    private function scoredKeysByAssessment(array $assessments): array
    {
        if ($assessments === []) {
            return [];
        }

        $keys = [];
        $rows = AssessmentScore::query()
            ->whereIn('assessment_id', array_map(static fn (Assessment $assessment): int => $assessment->id, $assessments))
            ->current()
            ->get(['assessment_id', 'level', 'sub_criterion_id', 'guiding_point_id']);

        foreach ($rows as $row) {
            $keys[$row->assessment_id][ScorableUnit::keyFor($row->level, $row->nodeId())] = true;
        }

        return $keys;
    }
}

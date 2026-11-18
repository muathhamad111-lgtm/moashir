<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Assessments\ReviewProgress;
use App\Data\Assessments\ScorableUnit;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeScore;
use App\Services\Assessments\ScoringTreeBuilder;

/**
 * The committee assessor's scoring progress for a page of assignments, read with one score
 * query for the whole page: the same measure as the evaluation screen's progress.
 */
final readonly class CommitteeProgressReader
{
    public function __construct(private ScoringTreeBuilder $trees) {}

    /**
     * @param  list<CommitteeAssignment>  $assignments
     * @return array<int, ReviewProgress>
     */
    public function readMany(array $assignments): array
    {
        $scoredKeys = $this->scoredKeysByAssignment($assignments);
        $treesByVersion = [];
        $progress = [];

        foreach ($assignments as $assignment) {
            $assessment = $assignment->assessment;
            $tree = $treesByVersion[$assessment->criteria_version_id] ??= $this->trees->build($assessment->loadMissing('criteriaVersion')->criteriaVersion);
            $progress[$assignment->id] = ReviewProgress::of($tree, $scoredKeys[$assignment->id] ?? []);
        }

        return $progress;
    }

    /**
     * @param  list<CommitteeAssignment>  $assignments
     * @return array<int, array<string, true>>
     */
    private function scoredKeysByAssignment(array $assignments): array
    {
        if ($assignments === []) {
            return [];
        }

        $keys = [];
        $rows = CommitteeScore::query()
            ->whereIn('committee_assignment_id', array_map(static fn (CommitteeAssignment $assignment): int => $assignment->id, $assignments))
            ->current()
            ->get(['committee_assignment_id', 'level', 'sub_criterion_id', 'guiding_point_id']);

        foreach ($rows as $row) {
            $keys[$row->committee_assignment_id][ScorableUnit::keyFor($row->level, $row->nodeId())] = true;
        }

        return $keys;
    }
}

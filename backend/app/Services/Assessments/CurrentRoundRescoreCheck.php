<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoringTree;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class CurrentRoundRescoreCheck
{
    public function __construct(private CurrentCompletionRound $rounds, private ScoreSheetReader $scores) {}

    public function assertRescored(Assessment $locked, ScoringTree $tree): void
    {
        $round = $this->rounds->requestFor($locked);

        if (! $round instanceof AssessmentCompletionRequest || ! $round->status->asksForRescore()) {
            return;
        }

        $rescored = $this->unitKeysScoredIn($locked, $round->id);
        $missing = array_values(array_filter(
            $tree->scorableUnitsUnder($round->requestedSubCodes()),
            static fn (ScorableUnit $unit): bool => ! isset($rescored[$unit->key()]),
        ));

        if ($missing !== []) {
            throw AssessmentRejected::roundRescoreMissing($missing);
        }
    }

    /**
     * @return array<string, true>
     */
    private function unitKeysScoredIn(Assessment $assessment, int $roundId): array
    {
        $keys = [];

        foreach ($this->scores->currentRows($assessment) as $row) {
            if ($row->completion_request_id === $roundId) {
                $keys[ScorableUnit::keyFor($row->level, $row->nodeId())] = true;
            }
        }

        return $keys;
    }
}

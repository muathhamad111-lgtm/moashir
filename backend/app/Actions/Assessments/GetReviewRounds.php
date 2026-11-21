<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\ReviewRounds;
use App\Data\Assessments\RoundScore;
use App\Data\Assessments\ScorableUnit;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Assessments\AssessmentScore;
use App\Services\Assessments\BoundSubCriteriaReader;

final readonly class GetReviewRounds
{
    private const int FIRST_ROUND = 1;

    public function __construct(private BoundSubCriteriaReader $subs) {}

    public function handle(Assessment $assessment): ReviewRounds
    {
        $requests = array_values(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->orderBy('id')->get()->all());

        return new ReviewRounds(
            $requests,
            $requests === [] ? null : $this->subs->of($assessment->criteriaVersion),
            $this->lastScorePerRound($assessment, $this->roundsByRequest($requests)),
        );
    }

    /**
     * @param  list<AssessmentCompletionRequest>  $requests
     * @return array<int, int>
     */
    private function roundsByRequest(array $requests): array
    {
        $byRequest = [];

        foreach ($requests as $position => $request) {
            $byRequest[$request->id] = ReviewRounds::roundAt($position);
        }

        return $byRequest;
    }

    /**
     * @param  array<int, int>  $roundsByRequest
     * @return array<string, list<RoundScore>>
     */
    private function lastScorePerRound(Assessment $assessment, array $roundsByRequest): array
    {
        $latest = [];

        $rows = AssessmentScore::query()
            ->where('assessment_id', $assessment->id)
            ->orderBy('id')
            ->get(['id', 'level', 'sub_criterion_id', 'guiding_point_id', 'raw_value', 'percent', 'completion_request_id', 'created_at']);

        foreach ($rows as $row) {
            $round = $row->completion_request_id === null ? self::FIRST_ROUND : ($roundsByRequest[$row->completion_request_id] ?? self::FIRST_ROUND);
            $latest[ScorableUnit::inputNameFor($row->level, $row->nodeId())][$round] = new RoundScore($round, $row);
        }

        return array_map(static function (array $byRound): array {
            ksort($byRound);

            return array_values($byRound);
        }, $latest);
    }
}

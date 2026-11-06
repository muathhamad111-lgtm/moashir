<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Insights\InsightsSubject;
use App\Data\Insights\ResultCandidate;
use App\Enums\Insights\ResultIneligibility;
use App\Exceptions\Insights\InsightsRejected;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\FeedbackSheetReader;
use App\Support\Insights\EligibleResultSelector;
use App\Support\Insights\ResultEligibility;

final readonly class InsightsSubjectLoader
{
    public function __construct(
        private AccreditedResultsReader $results,
        private ResultEligibility $eligibility,
        private EligibleResultSelector $selector,
        private ResultTreeReader $trees,
        private FeedbackSheetReader $feedback,
        private InsightsRulesReader $rules,
    ) {}

    public function load(Assessment $assessment): InsightsSubject
    {
        $candidates = $this->candidatesOf($assessment);
        $selected = $this->eligibleCandidate($assessment, $candidates);
        $previous = $this->selector->previousOf($selected, $candidates);

        return new InsightsSubject(
            $selected,
            $previous,
            $this->trees->of($selected),
            $previous instanceof ResultCandidate ? $this->trees->of($previous) : null,
            $this->feedback->read($selected->assessment),
            $this->rules->read(),
        );
    }

    public function eligibleResultOf(Assessment $assessment): ResultCandidate
    {
        return $this->eligibleCandidate($assessment, $this->candidatesOf($assessment));
    }

    /**
     * @return list<ResultCandidate>
     */
    private function candidatesOf(Assessment $assessment): array
    {
        return $assessment->isAccredited() ? $this->results->forEntity($assessment->entity_id) : [];
    }

    /**
     * @param  list<ResultCandidate>  $candidates
     */
    private function eligibleCandidate(Assessment $assessment, array $candidates): ResultCandidate
    {
        $candidate = array_find($candidates, static fn (ResultCandidate $candidate): bool => $candidate->id() === $assessment->id);
        $reason = $candidate instanceof ResultCandidate
            ? $this->eligibility->reasonFor($candidate)
            : $this->eligibility->reasonOf($assessment, null);

        if ($reason instanceof ResultIneligibility || ! $candidate instanceof ResultCandidate) {
            throw InsightsRejected::resultNotEligible($reason ?? ResultIneligibility::NotCompleted);
        }

        return $candidate;
    }
}

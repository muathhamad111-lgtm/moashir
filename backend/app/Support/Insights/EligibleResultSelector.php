<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\ResultCandidate;
use App\Data\Insights\ResultExclusion;
use App\Enums\Insights\ResultIneligibility;

final readonly class EligibleResultSelector
{
    public function __construct(private ResultEligibility $eligibility) {}

    /**
     * @param  list<ResultCandidate>  $candidates
     * @return list<ResultCandidate>
     */
    public function eligible(array $candidates): array
    {
        $eligible = array_values(array_filter($candidates, fn (ResultCandidate $candidate): bool => ! $this->eligibility->reasonFor($candidate) instanceof ResultIneligibility));
        usort($eligible, self::newestFirst(...));

        return $eligible;
    }

    /**
     * @param  list<ResultCandidate>  $candidates
     */
    public function latest(array $candidates): ?ResultCandidate
    {
        return $this->eligible($candidates)[0] ?? null;
    }

    /**
     * @param  list<ResultCandidate>  $candidates
     */
    public function previousOf(ResultCandidate $selected, array $candidates): ?ResultCandidate
    {
        return array_find($this->eligible($candidates), static fn (ResultCandidate $candidate): bool => self::newestFirst($selected, $candidate) < 0);
    }

    /**
     * @param  list<ResultCandidate>  $candidates
     * @return list<ResultExclusion>
     */
    public function exclusionsNewerThan(?ResultCandidate $selected, array $candidates): array
    {
        $exclusions = [];

        foreach ($candidates as $candidate) {
            $reason = $this->eligibility->reasonFor($candidate);

            if ($reason instanceof ResultIneligibility && $reason->isExclusion() && (! $selected instanceof ResultCandidate || self::newestFirst($candidate, $selected) < 0)) {
                $exclusions[] = new ResultExclusion($candidate, $reason);
            }
        }

        usort($exclusions, static fn (ResultExclusion $left, ResultExclusion $right): int => self::newestFirst($left->candidate, $right->candidate));

        return $exclusions;
    }

    private static function newestFirst(ResultCandidate $left, ResultCandidate $right): int
    {
        $leftAt = $left->verifiedAt()?->getTimestamp() ?? PHP_INT_MAX;
        $rightAt = $right->verifiedAt()?->getTimestamp() ?? PHP_INT_MAX;

        return [$rightAt, $right->id()] <=> [$leftAt, $left->id()];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Contracts\Criteria\BoundAssessmentCounter;
use App\Data\Criteria\BoundAssessmentCount;
use App\Data\Criteria\CriteriaVersionSummary;
use App\Models\Criteria\CriteriaVersion;

final readonly class CriteriaVersionSummarizer
{
    public function __construct(private BoundAssessmentCounter $boundAssessments) {}

    /**
     * @param  iterable<CriteriaVersion>  $versions
     * @return list<CriteriaVersionSummary>
     */
    public function summarize(iterable $versions): array
    {
        $versions = is_array($versions) ? array_values($versions) : iterator_to_array($versions, false);
        $counts = $this->boundAssessments->countsFor(array_map(static fn (CriteriaVersion $version): int => $version->id, $versions));

        return array_map(
            static fn (CriteriaVersion $version): CriteriaVersionSummary => new CriteriaVersionSummary($version, $counts[$version->id] ?? BoundAssessmentCount::none()),
            $versions,
        );
    }
}

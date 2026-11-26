<?php

declare(strict_types=1);

namespace App\Data\Insights;

final readonly class InsightsOverview
{
    /**
     * @param  list<ResultExclusion>  $exclusions
     */
    public function __construct(
        public ?ResultCandidate $selected,
        public array $exclusions,
        public ?InsightsEmptyState $emptyState,
        public ?int $inProgressRequestId,
        public InsightsRules $rules,
        public bool $offersActions,
    ) {}
}

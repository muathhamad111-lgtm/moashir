<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Models\Criteria\CriteriaVersion;

/**
 * What publishing a draft would change: the node changes against the published version, their labels
 * and the open requests a publication with scope "all" or "reset_open" would move to the draft.
 */
final readonly class CriteriaPublicationPreview
{
    /**
     * @param  list<CriteriaChange>  $changes
     * @param  array<string, array{ar: string, en: string}|null>  $labels  keyed by "node_type:code"
     */
    public function __construct(
        public ?CriteriaVersion $replaces,
        public array $changes,
        public array $labels,
        public int $rebindableOpenAssessments,
    ) {}

    public function scoreAffectingCount(): int
    {
        return count(array_filter($this->changes, static fn (CriteriaChange $change): bool => $change->isScoreAffecting()));
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    public function labelOf(CriteriaChange $change): ?array
    {
        return $this->labels[$change->nodeType->value.':'.$change->code] ?? null;
    }
}

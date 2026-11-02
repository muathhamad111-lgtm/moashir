<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Criteria\CriteriaEditEffect;

final readonly class CriteriaRebind
{
    /**
     * @param  list<string>  $rescoredCodes
     */
    public function __construct(
        public ScoringTree $from,
        public ScoringTree $to,
        public CriteriaEditEffect $effect,
        public array $rescoredCodes,
    ) {}

    public function isRescored(string $code): bool
    {
        return in_array($code, $this->rescoredCodes, true);
    }
}

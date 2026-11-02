<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Support\ValueObjects\Percent;

final readonly class CycleDefaults
{
    public function __construct(
        public int $minEntityAgeYears,
        public Percent $disqualificationScore,
        public int $initialReviewSlaDays,
    ) {}

    /**
     * @return array{min_entity_age_years: int, disqualification_score: string, initial_review_sla_days: int}
     */
    public function toArray(): array
    {
        return [
            'min_entity_age_years' => $this->minEntityAgeYears,
            'disqualification_score' => $this->disqualificationScore->toDisplay(),
            'initial_review_sla_days' => $this->initialReviewSlaDays,
        ];
    }
}

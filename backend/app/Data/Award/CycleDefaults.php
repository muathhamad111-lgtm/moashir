<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\Percent;

final readonly class CycleDefaults
{
    public function __construct(
        public int $minEntityAgeYears,
        public Percent $disqualificationScore,
        public int $initialReviewSlaDays,
        public Money $entryFee,
    ) {}

    /**
     * @return array{min_entity_age_years: int, disqualification_score: string, initial_review_sla_days: int, entry_fee: string}
     */
    public function toArray(): array
    {
        return [
            'min_entity_age_years' => $this->minEntityAgeYears,
            'disqualification_score' => $this->disqualificationScore->toDisplay(),
            'initial_review_sla_days' => $this->initialReviewSlaDays,
            'entry_fee' => $this->entryFee->amount,
        ];
    }
}

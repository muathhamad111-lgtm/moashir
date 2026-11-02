<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;

final readonly class MainResult
{
    /**
     * @param  list<SubResult>  $subs
     */
    public function __construct(
        public ScoringMain $main,
        public Percent $percent,
        public EarnedScore $earned,
        public array $subs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->main->id,
            'code' => $this->main->code,
            'weight' => $this->main->weight->toDecimal(),
            'percent' => $this->percent->toDecimal(),
            'earned' => $this->earned->toDecimal(),
            'subs' => array_map(static fn (SubResult $sub): array => $sub->toArray(), $this->subs),
        ];
    }
}

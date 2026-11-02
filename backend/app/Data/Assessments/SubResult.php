<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\RawScore;

final readonly class SubResult
{
    /**
     * @param  list<PointResult>  $points
     */
    public function __construct(
        public ScoringSub $sub,
        public ?RawScore $raw,
        public Percent $percent,
        public EarnedScore $earned,
        public array $points = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->sub->id,
            'code' => $this->sub->code,
            'weight' => $this->sub->weight->toDecimal(),
            'evaluation_type' => $this->sub->evaluationType->value,
            'raw_value' => $this->raw?->toDecimal(),
            'percent' => $this->percent->toDecimal(),
            'earned' => $this->earned->toDecimal(),
            'points' => array_map(static fn (PointResult $point): array => $point->toArray(), $this->points),
        ];
    }
}

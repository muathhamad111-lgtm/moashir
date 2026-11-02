<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\RawScore;

final readonly class PointResult
{
    public function __construct(
        public ScoringPoint $point,
        public ?RawScore $raw,
        public Percent $percent,
        public EarnedScore $earned,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->point->id,
            'code' => $this->point->code,
            'weight' => $this->point->weight->toDecimal(),
            'raw_value' => $this->raw?->toDecimal(),
            'percent' => $this->percent->toDecimal(),
            'earned' => $this->earned->toDecimal(),
        ];
    }
}

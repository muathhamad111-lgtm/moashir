<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\DeltaComparison;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\PointsDelta;

final readonly class MainDelta
{
    /**
     * @param  array<string, PointsDelta>  $subDeltas
     */
    public function __construct(
        public DeltaComparison $comparison,
        public ?PointsDelta $delta = null,
        public ?Percent $previousPercent = null,
        public array $subDeltas = [],
    ) {}

    public function forSub(string $code): ?PointsDelta
    {
        return $this->subDeltas[$code] ?? null;
    }
}

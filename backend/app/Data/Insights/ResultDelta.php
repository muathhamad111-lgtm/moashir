<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\DeltaComparison;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\PointsDelta;

final readonly class ResultDelta
{
    /**
     * @param  array<string, MainDelta>  $mains
     * @param  list<ResultMain>  $removed
     */
    public function __construct(
        public ?PointsDelta $total = null,
        public ?Percent $previousTotal = null,
        public bool $versionsDiffer = false,
        public array $mains = [],
        public array $removed = [],
    ) {}

    public function forMain(string $code): MainDelta
    {
        return $this->mains[$code] ?? new MainDelta(DeltaComparison::NoPrevious);
    }

    public function hasPrevious(): bool
    {
        return $this->previousTotal instanceof Percent;
    }
}

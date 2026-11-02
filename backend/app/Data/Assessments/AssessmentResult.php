<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\Weight;

final readonly class AssessmentResult
{
    /**
     * @param  list<MainResult>  $mains
     */
    public function __construct(
        public EarnedScore $total,
        public Weight $totalWeight,
        public Percent $percent,
        public array $mains,
    ) {}

    /**
     * @return array{mains: list<array<string, mixed>>}
     */
    public function breakdown(): array
    {
        return ['mains' => array_map(static fn (MainResult $main): array => $main->toArray(), $this->mains)];
    }
}

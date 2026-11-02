<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\LocalizedText;

final readonly class CriteriaNodeNames
{
    /**
     * @param  array<int, LocalizedText>  $mains
     * @param  array<int, LocalizedText>  $subs
     * @param  array<int, LocalizedText>  $points
     */
    public function __construct(private array $mains, private array $subs, private array $points) {}

    public function main(int $id): ?LocalizedText
    {
        return $this->mains[$id] ?? null;
    }

    public function sub(int $id): ?LocalizedText
    {
        return $this->subs[$id] ?? null;
    }

    public function point(int $id): ?LocalizedText
    {
        return $this->points[$id] ?? null;
    }
}

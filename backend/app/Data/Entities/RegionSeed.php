<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Support\ValueObjects\LocalizedText;

final readonly class RegionSeed
{
    /**
     * @param  list<CitySeed>  $cities
     */
    public function __construct(
        public string $code,
        public LocalizedText $name,
        public int $sortOrder,
        public array $cities,
    ) {}
}

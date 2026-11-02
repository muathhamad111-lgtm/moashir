<?php

declare(strict_types=1);

namespace App\Data\Entities;

final readonly class RegionInstallResult
{
    public function __construct(
        public int $regionsAdded,
        public int $citiesAdded,
    ) {}
}

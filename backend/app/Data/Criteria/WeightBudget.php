<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\Weight;

final readonly class WeightBudget
{
    public function __construct(
        public Weight $capacity,
        public Weight $allocatedToOthers,
        public bool $distributesEqually,
    ) {}
}

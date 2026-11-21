<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\Weight;

final readonly class WeightAssignment
{
    public function __construct(public int $nodeId, public Weight $weight) {}
}

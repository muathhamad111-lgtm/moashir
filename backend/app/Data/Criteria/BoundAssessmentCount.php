<?php

declare(strict_types=1);

namespace App\Data\Criteria;

final readonly class BoundAssessmentCount
{
    public function __construct(public int $total, public int $open) {}

    public static function none(): self
    {
        return new self(0, 0);
    }
}

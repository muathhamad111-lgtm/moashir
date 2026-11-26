<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class AssessmentCounts
{
    public function __construct(public int $total, public int $open, public int $withResult) {}

    /**
     * @return array{total: int, open: int, with_result: int}
     */
    public function toArray(): array
    {
        return ['total' => $this->total, 'open' => $this->open, 'with_result' => $this->withResult];
    }
}

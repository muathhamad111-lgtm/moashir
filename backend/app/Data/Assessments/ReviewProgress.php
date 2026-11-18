<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ReviewProgress
{
    public function __construct(public int $scored, public int $required) {}

    /**
     * @return array{scored: int, required: int}
     */
    public function toArray(): array
    {
        return ['scored' => $this->scored, 'required' => $this->required];
    }
}

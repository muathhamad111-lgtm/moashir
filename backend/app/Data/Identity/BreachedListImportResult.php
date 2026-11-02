<?php

declare(strict_types=1);

namespace App\Data\Identity;

final readonly class BreachedListImportResult
{
    public function __construct(
        public int $read,
        public int $added,
        public int $rejected,
    ) {}

    public function alreadyListed(): int
    {
        return $this->read - $this->rejected - $this->added;
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Entities;

final readonly class PermanentDeletionInstruction
{
    public function __construct(
        public string $reason,
        public string $confirmation,
        public bool $disclaimerAcknowledged,
    ) {}
}

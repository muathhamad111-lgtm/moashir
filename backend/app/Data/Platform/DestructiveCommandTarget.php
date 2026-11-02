<?php

declare(strict_types=1);

namespace App\Data\Platform;

final readonly class DestructiveCommandTarget
{
    public function __construct(
        public string $environment,
        public ?string $database,
        public ?string $confirmation = null,
    ) {}

    public function isConfirmed(): bool
    {
        return $this->database !== null && $this->confirmation === $this->database;
    }
}

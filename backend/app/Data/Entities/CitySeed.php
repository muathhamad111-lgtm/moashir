<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Support\ValueObjects\LocalizedText;

final readonly class CitySeed
{
    public function __construct(
        public string $code,
        public LocalizedText $name,
        public int $sortOrder,
    ) {}
}

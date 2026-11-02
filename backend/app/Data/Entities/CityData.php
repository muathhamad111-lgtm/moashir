<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Support\ValueObjects\LocalizedText;

final readonly class CityData
{
    public function __construct(
        public LocalizedText $name,
        public ?string $code = null,
        public ?int $sortOrder = null,
    ) {}
}

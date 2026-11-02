<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\BreachedListFormat;

final readonly class BreachedListImport
{
    public function __construct(
        public string $path,
        public BreachedListFormat $format,
    ) {}
}

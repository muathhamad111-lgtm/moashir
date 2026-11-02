<?php

declare(strict_types=1);

namespace App\Data\Files;

use Carbon\CarbonImmutable;

final readonly class SignedDownload
{
    public function __construct(
        public string $url,
        public CarbonImmutable $expiresAt,
        public string $fileName,
    ) {}
}

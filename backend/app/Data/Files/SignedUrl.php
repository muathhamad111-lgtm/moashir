<?php

declare(strict_types=1);

namespace App\Data\Files;

use Carbon\CarbonImmutable;

final readonly class SignedUrl
{
    public function __construct(public string $url, public CarbonImmutable $expiresAt) {}
}

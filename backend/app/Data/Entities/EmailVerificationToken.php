<?php

declare(strict_types=1);

namespace App\Data\Entities;

use Carbon\CarbonImmutable;

final readonly class EmailVerificationToken
{
    public function __construct(public string $token, public CarbonImmutable $expiresAt) {}
}

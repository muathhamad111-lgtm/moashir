<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Support\ValueObjects\SecretValue;

final readonly class DemoAccount
{
    public function __construct(
        public string $role,
        public string $name,
        public string $email,
        public SecretValue $password,
        public bool $mfaRequired,
    ) {}
}

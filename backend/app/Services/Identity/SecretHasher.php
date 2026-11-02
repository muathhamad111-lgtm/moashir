<?php

declare(strict_types=1);

namespace App\Services\Identity;

use Illuminate\Config\Repository;

final readonly class SecretHasher
{
    public function __construct(private Repository $config) {}

    public function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->config->string('app.key'));
    }

    public function matches(string $secret, string $hash): bool
    {
        return hash_equals($hash, $this->hash($secret));
    }
}

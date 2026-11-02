<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Support\ValueObjects\SecretValue;

final readonly class CredentialChanges
{
    /**
     * @param  array<string, mixed>  $publicConfig
     * @param  array<string, SecretValue|null>  $secrets
     */
    public function __construct(public array $publicConfig = [], public array $secrets = []) {}

    public function isEmpty(): bool
    {
        return $this->publicConfig === [] && $this->secrets === [];
    }

    public function touchesSecrets(): bool
    {
        return $this->secrets !== [];
    }
}

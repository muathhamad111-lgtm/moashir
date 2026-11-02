<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

final readonly class SecretValue
{
    private const string REDACTED = '[redacted]';

    public function __construct(#[SensitiveParameter] private string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('A secret cannot be empty.');
        }
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function last4(): string
    {
        return mb_substr($this->value, -4);
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => self::REDACTED];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('Secrets are never serialized.');
    }
}

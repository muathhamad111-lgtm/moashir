<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class UnifiedNumber
{
    public const string PATTERN = '/^[0-9]{10}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('A unified national number has exactly 10 digits.');
        }
    }

    public static function tryFrom(string $value): ?self
    {
        return preg_match(self::PATTERN, $value) === 1 ? new self($value) : null;
    }
}

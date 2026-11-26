<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class VatRegistrationNumber
{
    public const string PATTERN = '/^3[0-9]{13}3$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('A VAT registration number has 15 Western digits and starts and ends with 3.');
        }
    }

    public static function tryFrom(mixed $value): ?self
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1 ? new self($value) : null;
    }
}

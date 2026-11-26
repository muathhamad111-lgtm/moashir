<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class LicenseNumber
{
    public const string PATTERN = '/^[0-9]{3,20}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('A licence number has 3 to 20 digits.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class KpiNumber implements JsonSerializable
{
    public const string PATTERN = '/^-?\d{1,14}(\.\d{1,4})?$/';

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('A KPI number has at most 14 integer digits and 4 decimals.');
        }

        $this->value = $this->normalize($value);
    }

    public static function tryFrom(mixed $value): ?self
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && preg_match(self::PATTERN, trim($value)) === 1 ? new self($value) : null;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private function normalize(string $value): string
    {
        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $integer = ltrim($integer, '0');
        $fraction = rtrim($fraction, '0');
        $number = ($integer === '' ? '0' : $integer).($fraction === '' ? '' : '.'.$fraction);

        return $negative && $number !== '0' ? '-'.$number : $number;
    }
}

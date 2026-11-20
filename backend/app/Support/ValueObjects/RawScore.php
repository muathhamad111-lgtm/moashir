<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Criteria\ScoringType;
use App\Support\FixedPoint;
use InvalidArgumentException;

final readonly class RawScore
{
    private const int DECIMALS = 2;

    private function __construct(public ScoringType $type, public int $hundredths)
    {
        if (! $type->accepts($hundredths)) {
            throw new InvalidArgumentException('A raw score must be a value its scoring type allows.');
        }
    }

    public static function on(ScoringType $type, string|int|float $value): self
    {
        $text = is_float($value) ? number_format($value, self::DECIMALS, '.', '') : (string) $value;
        $parsed = FixedPoint::parse($text, self::DECIMALS);

        return $parsed === null
            ? throw new InvalidArgumentException('A raw score must be a decimal number with at most two decimal places.')
            : new self($type, $parsed);
    }

    public function percent(): Percent
    {
        return Percent::fromTenThousandths(intdiv($this->hundredths * Percent::HUNDRED, $this->type->maximumHundredths()));
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->hundredths === $other->hundredths;
    }

    public function toDecimal(): string
    {
        return FixedPoint::format($this->hundredths, self::DECIMALS);
    }
}

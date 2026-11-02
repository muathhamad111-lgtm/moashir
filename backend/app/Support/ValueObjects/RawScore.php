<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Criteria\ScaleKind;
use App\Support\FixedPoint;
use InvalidArgumentException;

final readonly class RawScore
{
    private const int DECIMALS = 2;

    private function __construct(public ScaleKind $scale, public int $hundredths)
    {
        if ($hundredths < 0 || $hundredths > $scale->maximumHundredths()) {
            throw new InvalidArgumentException('A raw score must lie between zero and the maximum of its scale.');
        }
    }

    public static function on(ScaleKind $scale, string|int|float $value): self
    {
        $text = is_float($value) ? number_format($value, self::DECIMALS, '.', '') : (string) $value;
        $parsed = FixedPoint::parse($text, self::DECIMALS);

        return $parsed === null
            ? throw new InvalidArgumentException('A raw score must be a decimal number with at most two decimal places.')
            : new self($scale, $parsed);
    }

    public function percent(): Percent
    {
        return Percent::fromTenThousandths(intdiv($this->hundredths * Percent::HUNDRED, $this->scale->maximumHundredths()));
    }

    public function equals(self $other): bool
    {
        return $this->scale === $other->scale && $this->hundredths === $other->hundredths;
    }

    public function toDecimal(): string
    {
        return FixedPoint::format($this->hundredths, self::DECIMALS);
    }
}

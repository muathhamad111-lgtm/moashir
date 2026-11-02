<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class Weight
{
    private const string DECIMAL_PATTERN = '/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/';

    private const int SCALE = 100;

    private function __construct(public int $hundredths) {}

    public static function of(string|int|float $value): self
    {
        $text = is_float($value) ? number_format($value, 2, '.', '') : trim((string) $value);

        if (preg_match(self::DECIMAL_PATTERN, $text, $parts) !== 1) {
            throw new InvalidArgumentException('A weight must be a decimal number with at most two decimal places.');
        }

        $magnitude = (int) $parts[2] * self::SCALE + (int) str_pad($parts[3] ?? '', 2, '0');

        return new self($parts[1] === '-' ? -$magnitude : $magnitude);
    }

    public static function fromHundredths(int $hundredths): self
    {
        return new self($hundredths);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * @param  iterable<self>  $weights
     */
    public static function sum(iterable $weights): self
    {
        $total = 0;

        foreach ($weights as $weight) {
            $total += $weight->hundredths;
        }

        return new self($total);
    }

    public function plus(self $other): self
    {
        return new self($this->hundredths + $other->hundredths);
    }

    public function minus(self $other): self
    {
        return new self($this->hundredths - $other->hundredths);
    }

    public function equals(self $other): bool
    {
        return $this->hundredths === $other->hundredths;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->hundredths > $other->hundredths;
    }

    public function isLessThan(self $other): bool
    {
        return $this->hundredths < $other->hundredths;
    }

    public function isPositive(): bool
    {
        return $this->hundredths > 0;
    }

    public function toDecimal(): string
    {
        $sign = $this->hundredths < 0 ? '-' : '';
        $magnitude = abs($this->hundredths);

        return sprintf('%s%d.%02d', $sign, intdiv($magnitude, self::SCALE), $magnitude % self::SCALE);
    }
}

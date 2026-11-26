<?php

declare(strict_types=1);

namespace App\Data\Periods;

final readonly class PeriodFilter
{
    public const string WITHOUT_PERIOD = 'none';

    public function __construct(public ?int $periodId) {}

    public static function fromInput(mixed $value): ?self
    {
        if ($value === self::WITHOUT_PERIOD) {
            return new self(null);
        }

        return is_string($value) && ctype_digit($value) ? new self((int) $value) : null;
    }

    public function isWithoutPeriod(): bool
    {
        return $this->periodId === null;
    }
}

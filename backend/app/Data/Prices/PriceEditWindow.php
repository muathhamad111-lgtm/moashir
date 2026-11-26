<?php

declare(strict_types=1);

namespace App\Data\Prices;

use Carbon\CarbonImmutable;

final readonly class PriceEditWindow
{
    public const string PRICE_MAX = '9999999.99';

    public const int MAX_DAYS_AHEAD = 365;

    public function __construct(public CarbonImmutable $firstDate, public CarbonImmutable $lastDate, public string $timezone) {}

    public static function at(CarbonImmutable $now, string $timezone): self
    {
        $today = $now->setTimezone($timezone)->startOfDay();

        return new self($today->addDay(), $today->addDays(self::MAX_DAYS_AHEAD), $timezone);
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->firstDate, $this->lastDate);
    }

    public function startOf(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone)->startOfDay();
    }

    /**
     * @return array{price_max: string, effective_from_min: string, effective_from_max: string}
     */
    public function toArray(): array
    {
        return [
            'price_max' => self::PRICE_MAX,
            'effective_from_min' => $this->firstDate->toDateString(),
            'effective_from_max' => $this->lastDate->toDateString(),
        ];
    }
}

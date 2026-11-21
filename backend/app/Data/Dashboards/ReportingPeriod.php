<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\OverviewPeriod;
use App\Enums\Dashboards\TrendBucket;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A half-open window [start, end) in the display time zone; `end` is exclusive.
 */
final readonly class ReportingPeriod
{
    public function __construct(
        public OverviewPeriod $preset,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if (! $end->greaterThan($start)) {
            throw new InvalidArgumentException('A reporting period must end after it starts.');
        }
    }

    /**
     * The window of the same length that ends where this one starts.
     */
    public function previous(): self
    {
        return new self($this->preset, $this->start->subSeconds($this->seconds()), $this->start);
    }

    public function days(): int
    {
        return (int) ceil($this->seconds() / CarbonImmutable::SECONDS_PER_MINUTE / CarbonImmutable::MINUTES_PER_HOUR / CarbonImmutable::HOURS_PER_DAY);
    }

    public function bucket(): TrendBucket
    {
        return TrendBucket::forDays($this->days());
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->end->subDay()->startOfDay();
    }

    private function seconds(): int
    {
        return (int) $this->start->diffInSeconds($this->end, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\ComparisonBasis;
use App\Enums\Dashboards\OverviewPeriod;
use App\Enums\Dashboards\TrendBucket;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A half-open window [start, end) in the display time zone; `end` is exclusive. A month, quarter
 * or year starts on its first day and ends at its natural end, or at the end of today while it runs.
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

    public function comparisonBasis(): ComparisonBasis
    {
        $months = $this->preset->months();

        if ($months === null) {
            return ComparisonBasis::PrecedingDays;
        }

        return $this->end->lessThan($this->start->addMonthsNoOverflow($months))
            ? ComparisonBasis::ElapsedSpan
            : ComparisonBasis::PreviousFull;
    }

    /**
     * The window this one is compared with (see ComparisonBasis).
     */
    public function previous(): self
    {
        return match ($this->comparisonBasis()) {
            ComparisonBasis::PrecedingDays => new self($this->preset, $this->start->subSeconds($this->seconds()), $this->start),
            ComparisonBasis::PreviousFull => new self($this->preset, $this->start->subMonthsNoOverflow($this->months()), $this->start),
            ComparisonBasis::ElapsedSpan => $this->sameSpanBefore(),
        };
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

    /**
     * The previous period's first days up to the same day of it, kept inside it (31 March → 28/29 February).
     */
    private function sameSpanBefore(): self
    {
        $months = $this->months();
        $end = $this->lastDay()->subMonthsNoOverflow($months)->addDay();

        return new self($this->preset, $this->start->subMonthsNoOverflow($months), $end->min($this->start));
    }

    private function months(): int
    {
        return $this->preset->months() ?? throw new InvalidArgumentException('A custom period has no calendar months.');
    }

    private function seconds(): int
    {
        return (int) $this->start->diffInSeconds($this->end, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Periods\PeriodCadence;
use App\Support\Periods\PeriodLabel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class PeriodWindow
{
    public CarbonImmutable $startsOn;

    public CarbonImmutable $endsOn;

    public function __construct(
        public PeriodCadence $cadence,
        public int $year,
        public int $sequence,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
    ) {
        $this->startsOn = $startsOn->startOfDay();
        $this->endsOn = $endsOn->startOfDay();

        if ($sequence < 1 || $sequence > $cadence->periodsPerYear() || $this->endsOn->lessThan($this->startsOn)) {
            throw new InvalidArgumentException('A period window needs a valid sequence and an end on or after its start.');
        }
    }

    public static function containing(PeriodCadence $cadence, CarbonImmutable $day): self
    {
        $months = $cadence->monthsPerPeriod();
        $sequence = intdiv($day->month - 1, $months) + 1;
        $start = $day->setDate($day->year, ($sequence - 1) * $months + 1, 1)->startOfDay();

        return new self($cadence, $day->year, $sequence, $start, $start->addMonthsNoOverflow($months)->subDay());
    }

    public function key(): string
    {
        return PeriodLabel::keyOf($this->cadence, $this->year, $this->sequence);
    }

    public function label(): LocalizedText
    {
        return PeriodLabel::of($this->cadence, $this->year, $this->sequence);
    }

    public function isShortened(): bool
    {
        return ! $this->startsOn->equalTo(self::containing($this->cadence, $this->endsOn)->startsOn);
    }

    public function startingOn(CarbonImmutable $day): self
    {
        return new self($this->cadence, $this->year, $this->sequence, $day, $this->endsOn);
    }

    public function next(): self
    {
        return self::containing($this->cadence, $this->endsOn->addDay());
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->startsOn->utc();
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->endsOn->addDay()->utc();
    }

    public function sameAs(self $other): bool
    {
        return $this->cadence === $other->cadence
            && $this->key() === $other->key()
            && $this->startsOn->toDateString() === $other->startsOn->toDateString()
            && $this->endsOn->toDateString() === $other->endsOn->toDateString();
    }
}

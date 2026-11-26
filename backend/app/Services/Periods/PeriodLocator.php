<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\LocalDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class PeriodLocator
{
    public function current(): ?AssessmentPeriod
    {
        return $this->onDay(LocalDay::today());
    }

    public function containing(CarbonInterface $instant): ?AssessmentPeriod
    {
        return $this->onDay(LocalDay::of($instant));
    }

    public function onDay(CarbonImmutable $day): ?AssessmentPeriod
    {
        return AssessmentPeriod::query()->live()->containingDay($day)->first();
    }

    public function after(AssessmentPeriod $period): ?AssessmentPeriod
    {
        return AssessmentPeriod::query()->live()->where('starts_on', '>', $period->ends_on->toDateString())->orderBy('starts_on')->first();
    }

    public function firstUpcoming(): ?AssessmentPeriod
    {
        return AssessmentPeriod::query()->live()->startingAfter(LocalDay::today())->orderBy('starts_on')->first();
    }

    public function startingOn(CarbonImmutable $day): ?AssessmentPeriod
    {
        return AssessmentPeriod::query()->live()->where('starts_on', $day->toDateString())->first();
    }

    public function lastStarted(CarbonImmutable $today): ?AssessmentPeriod
    {
        return AssessmentPeriod::query()->live()->where('starts_on', '<=', $today->toDateString())->orderByDesc('starts_on')->first();
    }

    /**
     * @return Collection<int, AssessmentPeriod>
     */
    public function upcoming(): Collection
    {
        return AssessmentPeriod::query()->live()->startingAfter(LocalDay::today())->orderBy('starts_on')->get();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Events\Assessments\PeriodsSuperseded;
use App\Models\Assessments\AssessmentPeriod;
use App\Services\Settings\SettingsLock;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodPlanner;
use App\Support\ValueObjects\PeriodWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PeriodSchedule
{
    public const string LOCK = 'periodic';

    public function __construct(private PeriodicSettingsReader $settings, private PeriodLocator $periods, private SettingsLock $lock) {}

    /**
     * @return list<int>
     */
    public function sync(): array
    {
        return DB::transaction(function (): array {
            $this->lock->lock(self::LOCK);
            $today = LocalDay::today();
            $lastStarted = $this->periods->lastStarted($today);
            $plan = PeriodPlanner::plan($this->settings->read(), $today, $lastStarted?->window(LocalDay::timezone())->endsOn);

            foreach ($plan->started as $window) {
                AssessmentPeriod::query()->create(AssessmentPeriod::attributesOf($window));
            }

            $superseded = $this->supersedeUnplanned($plan->upcoming, $today);
            $this->createMissing($plan->upcoming, $today);

            if ($superseded !== []) {
                PeriodsSuperseded::dispatch($superseded);
            }

            return $superseded;
        });
    }

    /**
     * @param  list<PeriodWindow>  $planned
     * @return list<int>
     */
    private function supersedeUnplanned(array $planned, CarbonImmutable $today): array
    {
        $superseded = [];

        foreach (AssessmentPeriod::query()->live()->startingAfter($today)->orderBy('starts_on')->get() as $period) {
            if (! $this->isPlanned($period->window(LocalDay::timezone()), $planned)) {
                $period->forceFill(['superseded_at' => Date::now()])->save();
                $superseded[] = $period->id;
            }
        }

        return $superseded;
    }

    /**
     * @param  list<PeriodWindow>  $planned
     */
    private function createMissing(array $planned, CarbonImmutable $today): void
    {
        $existing = array_values(array_map(
            static fn (AssessmentPeriod $period): PeriodWindow => $period->window(LocalDay::timezone()),
            AssessmentPeriod::query()->live()->startingAfter($today)->get()->all(),
        ));

        foreach ($planned as $window) {
            if (! $this->isPlanned($window, $existing)) {
                AssessmentPeriod::query()->create(AssessmentPeriod::attributesOf($window));
            }
        }
    }

    /**
     * @param  list<PeriodWindow>  $windows
     */
    private function isPlanned(PeriodWindow $window, array $windows): bool
    {
        foreach ($windows as $candidate) {
            if ($candidate->sameAs($window)) {
                return true;
            }
        }

        return false;
    }
}

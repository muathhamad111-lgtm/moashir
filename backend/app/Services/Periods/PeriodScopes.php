<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Periods\PeriodFilter;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class PeriodScopes
{
    public function __construct(private PeriodicSettingsReader $settings) {}

    /**
     * @param  Builder<Assessment>  $query
     */
    public function submittedIn(Builder $query, PeriodFilter $filter): void
    {
        $filter->isWithoutPeriod() ? $query->whereNull('period_id') : $query->where('period_id', $filter->periodId);
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    public function openedIn(Builder $query, PeriodFilter $filter): void
    {
        if ($filter->isWithoutPeriod()) {
            $rollout = $this->settings->read()->rolloutFrom;

            if ($rollout instanceof CarbonImmutable) {
                $query->where('created_at', '<', $rollout->utc());
            }

            return;
        }

        $period = AssessmentPeriod::query()->find($filter->periodId);

        if (! $period instanceof AssessmentPeriod) {
            $query->whereRaw('false');

            return;
        }

        $query->where('created_at', '>=', $period->starts_at)->where('created_at', '<', $period->ends_at);
    }
}

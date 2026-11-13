<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Data\Periods\PeriodListFilters;
use App\Enums\Periods\PeriodTimeline;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\LocalDay;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAssessmentPeriods
{
    /**
     * @return LengthAwarePaginator<int, AssessmentPeriod>
     */
    public function handle(PeriodListFilters $filters): LengthAwarePaginator
    {
        $today = LocalDay::today()->toDateString();
        $query = AssessmentPeriod::query()->live();

        match ($filters->timeline) {
            PeriodTimeline::Past => $query->where('ends_on', '<', $today),
            PeriodTimeline::Current => $query->where('starts_on', '<=', $today)->where('ends_on', '>=', $today),
            PeriodTimeline::Upcoming => $query->where('starts_on', '>', $today),
            null => null,
        };

        return $query->orderByDesc('starts_on')->paginate($filters->perPage);
    }
}

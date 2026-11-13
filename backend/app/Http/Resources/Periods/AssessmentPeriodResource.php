<?php

declare(strict_types=1);

namespace App\Http\Resources\Periods;

use App\Enums\Periods\PeriodTimeline;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentPeriod $resource
 */
final class AssessmentPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...PeriodPresenter::period($this->resource), 'state' => $this->timelineOf($this->resource)->value];
    }

    private function timelineOf(AssessmentPeriod $period): PeriodTimeline
    {
        $today = LocalDay::today()->toDateString();

        return match (true) {
            $period->ends_on->toDateString() < $today => PeriodTimeline::Past,
            $period->starts_on->toDateString() > $today => PeriodTimeline::Upcoming,
            default => PeriodTimeline::Current,
        };
    }
}

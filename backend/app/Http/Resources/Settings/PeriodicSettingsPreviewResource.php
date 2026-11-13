<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Periods\PeriodicPreview;
use App\Data\Periods\PlannedPeriod;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PeriodicPreview $resource
 */
final class PeriodicSettingsPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preview = $this->resource;
        $noticeDays = $preview->settings->noticeDays;
        $upcoming = array_map(static fn (PlannedPeriod $planned): array => PeriodPresenter::planned($planned, $noticeDays), $preview->upcoming);

        return [
            'current_period' => PeriodPresenter::optional($preview->current),
            'cadence_effective_from' => $preview->cadenceEffectiveFrom?->toDateString(),
            'first_period' => $upcoming[0] ?? null,
            'upcoming_periods' => $upcoming,
            'removed_periods' => array_map(static fn (AssessmentPeriod $period): array => PeriodPresenter::period($period), $preview->removed),
            'corrections_to_send' => $preview->correctionsToSend,
        ];
    }
}

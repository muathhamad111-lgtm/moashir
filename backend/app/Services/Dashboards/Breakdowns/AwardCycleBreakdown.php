<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\FilteringOutcome;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Dashboards\AssessmentTotals;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;

final readonly class AwardCycleBreakdown implements OverviewBreakdownSource
{
    public function __construct(private AssessmentTotals $assessments, private Config $config) {}

    public function keys(): array
    {
        return [OverviewBreakdownKey::Award];
    }

    /**
     * @return array{award: array{cycle_year: int, status: string, submission_closed_on: string|null, announcement_on: string|null, applicants: int, qualified: int}|null}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $cycle = $this->newestOverlapping($period);

        if (! $cycle instanceof AwardCycle) {
            return [OverviewBreakdownKey::Award->value => null];
        }

        $applicants = $this->applicants($cycle, $filter);

        return [OverviewBreakdownKey::Award->value => [
            'cycle_year' => $cycle->year,
            'status' => $cycle->status->value,
            'submission_closed_on' => $this->day($cycle->submission_end),
            'announcement_on' => $this->day($cycle->announcement_at),
            'applicants' => (clone $applicants)->count(),
            'qualified' => $applicants->whereHas('latestFilteringDecision', static fn (Builder $decision): Builder => $decision
                ->where('outcome', FilteringOutcome::Qualified->value))->count(),
        ]];
    }

    private function newestOverlapping(ReportingPeriod $period): ?AwardCycle
    {
        return AwardCycle::query()
            ->where('submission_start', '<', $period->end->toIso8601String())
            ->whereRaw('coalesce(announcement_at, committee_end) >= ?', [$period->start->toIso8601String()])
            ->orderByDesc('submission_start')->orderByDesc('id')
            ->first();
    }

    /**
     * @return Builder<Assessment>
     */
    private function applicants(AwardCycle $cycle, BoardFilter $filter): Builder
    {
        $applicants = $this->assessments->operational()
            ->where('path', AssessmentPath::Advanced->value)
            ->where('award_cycle_id', $cycle->id)
            ->whereNotNull('submitted_at');
        $filter->regionOnly()->applyToEntityOwned($applicants, 'assessments.entity_id');

        return $applicants;
    }

    private function day(?CarbonInterface $moment): ?string
    {
        return $moment?->copy()->setTimezone($this->config->string('app.display_timezone'))->toDateString();
    }
}

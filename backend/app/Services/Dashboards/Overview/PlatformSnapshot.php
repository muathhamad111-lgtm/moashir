<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\DashboardWidget;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Services\Dashboards\AssessmentTotals;
use App\Services\Dashboards\CriteriaAverages;
use App\Services\Dashboards\EntityDistributions;
use App\Support\Dashboards\PeriodWindow;
use Illuminate\Database\Eloquent\Builder;

/**
 * The entities as they stood at the period's end (levels, sectors, founding decades), their governance
 * grades now, and the weakest main criteria across the accredited assessments completed in the period.
 */
final readonly class PlatformSnapshot
{
    private const int LOWEST_CRITERIA = 10;

    public function __construct(
        private EntityDistributions $entities,
        private EntityPopulation $population,
        private AssessmentTotals $assessments,
        private CriteriaAverages $criteria,
    ) {}

    /**
     * @return list<DashboardWidget>
     */
    public function widgets(ReportingPeriod $period, BoardFilter $filter): array
    {
        $atPeriodEnd = $this->population->asAt($period->end, $filter);

        return [
            new DashboardWidget('entities_by_level', $this->entities->byLevel($atPeriodEnd)),
            new DashboardWidget('entities_by_governance', $this->entities->byGovernance($this->population->current($filter))),
            new DashboardWidget('entities_by_sector', $this->entities->bySector($atPeriodEnd)),
            new DashboardWidget('entities_by_founding_decade', $this->entities->byFoundingDecade($atPeriodEnd)),
            new DashboardWidget('lowest_criteria', $this->criteria->lowestMains($this->completedAccredited($period, $filter), self::LOWEST_CRITERIA)),
        ];
    }

    public function activeEntities(BoardFilter $filter): int
    {
        return $this->entities->statusCounts($this->population->current($filter))['active'] ?? 0;
    }

    /**
     * @return Builder<Assessment>
     */
    private function completedAccredited(ReportingPeriod $period, BoardFilter $filter): Builder
    {
        $completed = $this->assessments->operational()
            ->where('path', AssessmentPath::Accredited)
            ->where('status', AccreditedAssessmentStatus::Completed->value);
        PeriodWindow::moments($completed, 'completed_at', $period);
        $filter->applyToEntityOwned($completed, 'assessments.entity_id');

        return $completed;
    }
}

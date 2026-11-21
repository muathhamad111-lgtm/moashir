<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\DashboardWidget;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Services\Dashboards\AssessmentTotals;
use App\Services\Dashboards\CriteriaAverages;
use App\Services\Dashboards\EntityDistributions;

/**
 * The platform as it stands now: what levels and sectors the entities hold and
 * the weakest main criteria across completed accredited assessments.
 */
final readonly class PlatformSnapshot
{
    private const int LOWEST_CRITERIA = 10;

    public function __construct(
        private EntityDistributions $entities,
        private AssessmentTotals $assessments,
        private CriteriaAverages $criteria,
    ) {}

    /**
     * @return list<DashboardWidget>
     */
    public function widgets(): array
    {
        return [
            new DashboardWidget('entities_by_level', $this->entities->byLevel()),
            new DashboardWidget('entities_by_governance', $this->entities->byGovernance()),
            new DashboardWidget('entities_by_sector', $this->entities->bySector()),
            new DashboardWidget('entities_by_founding_decade', $this->entities->byFoundingDecade()),
            new DashboardWidget('lowest_criteria', $this->criteria->lowestMains(
                $this->assessments->operational()->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Completed->value),
                self::LOWEST_CRITERIA,
            )),
        ];
    }

    public function activeEntities(): int
    {
        return $this->entities->statusCounts()['active'] ?? 0;
    }
}

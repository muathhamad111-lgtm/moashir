<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use App\Services\Dashboards\CriteriaAverages;
use App\Services\Dashboards\EntityDistributions;
use App\Services\Dashboards\PaymentTotals;
use App\Services\Dashboards\ResultAverages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final readonly class ExecutiveBoardQuery implements DashboardBoardQuery
{
    private const int LOWEST_CRITERIA = 10;

    public function __construct(
        private EntityDistributions $entities,
        private AssessmentTotals $assessments,
        private PaymentTotals $payments,
        private CriteriaAverages $criteria,
        private ResultAverages $results,
    ) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::Executive;
    }

    public function build(User $user): BoardContent
    {
        $entities = $this->entities->statusCounts();
        $totals = $this->assessments->overall(Date::now());

        return new BoardContent([
            new DashboardCard('entities_total', $entities['total'] ?? 0),
            new DashboardCard('entities_active', $entities['active'] ?? 0),
            new DashboardCard('self_assessments_completed', $totals['self_completed'] ?? 0),
            new DashboardCard('accredited_assessments_completed', $totals['accredited_completed'] ?? 0),
            new DashboardCard('advanced_requests', $totals['advanced_total'] ?? 0),
            new DashboardCard('accredited_average_percent', $this->results->averagePercent($this->completedAccredited()), CardFormat::Percent),
            new DashboardCard('award_cycles_total', AwardCycle::query()->count()),
            new DashboardCard('payments_collected', $this->payments->live()['collected'], CardFormat::Money),
        ], [
            new DashboardWidget('entities_by_region', $this->entities->byRegion()),
            new DashboardWidget('entities_by_sector', $this->entities->bySector()),
            new DashboardWidget('entities_by_governance', $this->entities->byGovernance()),
            new DashboardWidget('entities_by_level', $this->entities->byLevel()),
            new DashboardWidget('lowest_criteria', $this->criteria->lowestMains($this->completedAccredited(), self::LOWEST_CRITERIA)),
        ]);
    }

    /**
     * @return Builder<Assessment>
     */
    private function completedAccredited(): Builder
    {
        return $this->assessments->operational()->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Completed->value);
    }
}

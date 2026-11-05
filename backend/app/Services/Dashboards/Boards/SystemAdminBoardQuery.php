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
use App\Enums\Identity\UserStatus;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Dashboards\ActivityTimeline;
use App\Services\Dashboards\AssessmentTotals;
use App\Services\Dashboards\CriteriaAverages;
use App\Services\Dashboards\EntityDistributions;
use App\Services\Dashboards\PaymentTotals;
use App\Support\Dashboards\DashboardLinks as Links;
use Illuminate\Support\Facades\Date;

final readonly class SystemAdminBoardQuery implements DashboardBoardQuery
{
    private const int LOWEST_CRITERIA = 10;

    private const int ACTIVITY_ITEMS = 10;

    public function __construct(
        private EntityDistributions $entities,
        private AssessmentTotals $assessments,
        private PaymentTotals $payments,
        private CriteriaAverages $criteria,
        private ActivityTimeline $activity,
    ) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::SystemAdmin;
    }

    public function build(User $user): BoardContent
    {
        return new BoardContent([...$this->platformCards(), ...$this->assessmentCards(), ...$this->paymentCards()], [
            new DashboardWidget('entities_by_region', $this->entities->byRegion()),
            new DashboardWidget('entities_by_sector', $this->entities->bySector()),
            new DashboardWidget('entities_by_founding_decade', $this->entities->byFoundingDecade()),
            new DashboardWidget('entities_by_governance', $this->entities->byGovernance()),
            new DashboardWidget('lowest_criteria', $this->criteria->lowestMains(
                $this->assessments->operational()->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Completed->value),
                self::LOWEST_CRITERIA,
            )),
            new DashboardWidget('activity', $this->activity->latest(self::ACTIVITY_ITEMS)),
        ]);
    }

    /**
     * @return list<DashboardCard>
     */
    private function platformCards(): array
    {
        $entities = $this->entities->statusCounts();

        return [
            new DashboardCard('entities_total', $entities['total'] ?? 0, link: Links::ENTITIES),
            new DashboardCard('entities_active', $entities['active'] ?? 0, link: Links::ENTITIES),
            new DashboardCard('entities_pending_verification', $entities['pending_verification'] ?? 0, link: Links::ENTITIES),
            new DashboardCard('staff_users_active', User::query()->staff()->withoutSuperAdmins()->where('status', UserStatus::Active)->count(), link: Links::USERS),
            new DashboardCard('award_cycles_running', AwardCycle::query()->running()->count(), link: Links::CYCLES),
        ];
    }

    /**
     * @return list<DashboardCard>
     */
    private function assessmentCards(): array
    {
        $totals = $this->assessments->overall(Date::now());

        return [
            new DashboardCard('self_assessments', $totals['self_total'] ?? 0),
            new DashboardCard('accredited_requests', $totals['accredited_total'] ?? 0, link: Links::REQUESTS),
            new DashboardCard('advanced_requests', $totals['advanced_total'] ?? 0, link: Links::REQUESTS),
            new DashboardCard('open_requests', $totals['open_requests'] ?? 0, link: Links::REQUESTS),
            new DashboardCard('completed_assessments', ($totals['self_completed'] ?? 0) + ($totals['accredited_completed'] ?? 0) + ($totals['advanced_decided'] ?? 0)),
            new DashboardCard('overdue_requests', $totals['overdue'] ?? 0, link: Links::overdueRequests()),
        ];
    }

    /**
     * @return list<DashboardCard>
     */
    private function paymentCards(): array
    {
        $payments = $this->payments->live();

        return [
            new DashboardCard('payments_collected', $payments['collected'], CardFormat::Money, Links::FINANCE),
            new DashboardCard('payments_open', $payments['open_count'], link: Links::FINANCE_TRANSACTIONS),
            new DashboardCard('payments_paid', $payments['paid_count'], link: Links::FINANCE_TRANSACTIONS),
            new DashboardCard('refunds_total', $payments['refunded'], CardFormat::Money, Links::FINANCE_REFUNDS),
        ];
    }
}

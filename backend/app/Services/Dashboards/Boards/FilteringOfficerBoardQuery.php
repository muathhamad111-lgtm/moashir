<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Award\FilteringDecision;
use App\Models\Identity\User;
use App\Services\Dashboards\MemberCycleRequests;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;

final readonly class FilteringOfficerBoardQuery implements DashboardBoardQuery
{
    public function __construct(private MemberCycleRequests $requests) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::FilteringOfficer;
    }

    public function build(User $user): BoardContent
    {
        $qualified = Advanced::valuesOf(...Advanced::qualifiedStates());
        $counts = AggregateRow::counts($this->requests->of($user, CycleMemberRole::FilteringOfficer), [
            'awaiting_filtering' => ['status = ?', [Advanced::AwaitingFiltering->value]],
            'qualified' => ['status in ('.AggregateRow::placeholders($qualified).')', $qualified],
            'not_qualified' => ['status = ?', [Advanced::NotQualified->value]],
        ]);

        return new BoardContent([
            new DashboardCard('awaiting_filtering', $counts['awaiting_filtering'] ?? 0, link: Links::FILTERING),
            new DashboardCard('qualified', $counts['qualified'] ?? 0, link: Links::FILTERING),
            new DashboardCard('not_qualified', $counts['not_qualified'] ?? 0, link: Links::FILTERING),
            new DashboardCard('override_decisions', FilteringDecision::query()
                ->whereIn('award_cycle_id', $this->requests->cycleIds($user, CycleMemberRole::FilteringOfficer))
                ->where('is_override', true)
                ->count()),
        ], [
            new DashboardWidget('cycles', $this->requests->cycles($user, CycleMemberRole::FilteringOfficer)),
        ]);
    }
}

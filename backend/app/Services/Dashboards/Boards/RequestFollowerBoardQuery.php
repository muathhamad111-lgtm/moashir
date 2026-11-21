<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Assessments\AccreditedAssessmentStatus as Accredited;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Identity\User;
use App\Services\Dashboards\RequestPipeline;
use App\Services\Identity\RequestPathScope;
use App\Support\Assessments\PathPresenter;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\Dashboards\Ratio;
use Illuminate\Support\Facades\Date;

final readonly class RequestFollowerBoardQuery implements DashboardBoardQuery
{
    private const int ASSESSORS = 10;

    public function __construct(private RequestPipeline $pipeline, private RequestPathScope $paths) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::RequestFollower;
    }

    public function build(User $user): BoardContent
    {
        $followed = $this->paths->pathsFor($user);
        $counts = $this->counts($followed);
        $completed = $counts['accredited_completed'] + $counts['advanced_initial_done'];

        return new BoardContent([
            new DashboardCard('awaiting_assignment', $counts['awaiting_assignment'], link: Links::requestsWithStatus(Accredited::Submitted->value, Advanced::AwaitingReviewAssignment->value)),
            new DashboardCard('assigned', $counts['assigned'], link: Links::requestsWithStatus(Accredited::Assigned->value)),
            new DashboardCard('in_review', $counts['in_review'], link: Links::requestsWithStatus(Accredited::UnderReview->value, Advanced::UnderInitialAssessment->value)),
            new DashboardCard('needs_completion', $counts['needs_completion'], link: Links::requestsWithStatus(Accredited::NeedsCompletion->value)),
            new DashboardCard('completed', $completed, link: Links::requestsWithStatus(Accredited::Completed->value)),
            new DashboardCard('overdue', $counts['overdue'], link: Links::overdueRequests()),
            new DashboardCard('completion_rate', Ratio::percent($completed, $counts['total']), CardFormat::Percent),
        ], [
            new DashboardWidget('requests_by_path', array_map(
                static fn (AssessmentPath $path): array => [...PathPresenter::of($path), 'count' => $counts[$path->value]],
                $followed,
            )),
            new DashboardWidget('pipeline', $this->pipeline->byStatus($followed)),
            new DashboardWidget('assessors', $this->pipeline->busiestAssessors($followed, self::ASSESSORS)),
        ]);
    }

    /**
     * @param  list<AssessmentPath>  $followed
     * @return array<string, int>
     */
    private function counts(array $followed): array
    {
        $initialDone = Advanced::valuesOf(...Advanced::filteringVisibleStates());
        $clock = [...AssessmentPath::Accredited->reviewClockStateValues(), ...AssessmentPath::Advanced->reviewClockStateValues()];

        return AggregateRow::counts($this->pipeline->queue($followed), [
            'total' => ['true', []],
            'accredited' => ['path = ?', [AssessmentPath::Accredited->value]],
            'advanced' => ['path = ?', [AssessmentPath::Advanced->value]],
            'awaiting_assignment' => ['status in (?, ?)', [Accredited::Submitted->value, Advanced::AwaitingReviewAssignment->value]],
            'assigned' => ['status = ?', [Accredited::Assigned->value]],
            'in_review' => ['status in (?, ?)', [Accredited::UnderReview->value, Advanced::UnderInitialAssessment->value]],
            'needs_completion' => ['status = ?', [Accredited::NeedsCompletion->value]],
            'accredited_completed' => ['path = ? and status = ?', [AssessmentPath::Accredited->value, Accredited::Completed->value]],
            'advanced_initial_done' => ['path = ? and status in ('.AggregateRow::placeholders($initialDone).')', [AssessmentPath::Advanced->value, ...$initialDone]],
            'overdue' => ['due_at < ? and status in ('.AggregateRow::placeholders($clock).')', [Date::now()->toIso8601String(), ...$clock]],
        ]) + array_fill_keys(['total', 'accredited', 'advanced', 'awaiting_assignment', 'assigned', 'in_review', 'needs_completion', 'accredited_completed', 'advanced_initial_done', 'overdue'], 0);
    }
}

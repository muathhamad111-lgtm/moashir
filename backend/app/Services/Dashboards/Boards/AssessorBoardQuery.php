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
use App\Enums\Assessments\AssignmentStatus;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use App\Services\Dashboards\CriteriaAverages;
use App\Support\Assessments\PathPresenter;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final readonly class AssessorBoardQuery implements DashboardBoardQuery
{
    private const int DUE_SOON = 5;

    private const int WEAKEST_CRITERIA = 5;

    public function __construct(private AssessmentTotals $assessments, private CriteriaAverages $criteria) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::Assessor;
    }

    public function build(User $user): BoardContent
    {
        $open = $this->openCounts($user);
        $history = $this->history($user);

        return new BoardContent([
            new DashboardCard('to_start', $open['to_start'] ?? 0, link: Links::ASSIGNED_REQUESTS),
            new DashboardCard('in_progress', $open['in_progress'] ?? 0, link: Links::ASSIGNED_REQUESTS),
            new DashboardCard('awaiting_entity', $open['awaiting_entity'] ?? 0, link: Links::ASSIGNED_REQUESTS),
            new DashboardCard('overdue', $open['overdue'] ?? 0, link: Links::ASSIGNED_REQUESTS),
            new DashboardCard('completed', ScalarValue::int($history['completed'] ?? null), link: Links::ASSIGNED_REQUESTS),
            new DashboardCard('average_duration_days', ScalarValue::string($history['average_days'] ?? null) ?: null, CardFormat::Days),
        ], [
            new DashboardWidget('due_soon', $this->dueSoon($user)),
            new DashboardWidget('weakest_criteria', $this->criteria->lowestMains(
                $this->assessments->operational()->reviewedBy($user->id)->where('path', AssessmentPath::Accredited)->where('status', Accredited::Completed->value),
                self::WEAKEST_CRITERIA,
            )),
        ]);
    }

    /**
     * @return Builder<Assessment>
     */
    private function assigned(User $user): Builder
    {
        return $this->assessments->operational()->assignedTo($user->id);
    }

    /**
     * @return array<string, int>
     */
    private function openCounts(User $user): array
    {
        $clock = [...AssessmentPath::Accredited->reviewClockStateValues(), ...AssessmentPath::Advanced->reviewClockStateValues()];

        return AggregateRow::counts($this->assigned($user), [
            'to_start' => ['status = ?', [Accredited::Assigned->value]],
            'in_progress' => ['status in (?, ?)', [Accredited::UnderReview->value, Advanced::UnderInitialAssessment->value]],
            'awaiting_entity' => ['status = ?', [Accredited::NeedsCompletion->value]],
            'overdue' => ['due_at < ? and status in ('.AggregateRow::placeholders($clock).')', [Date::now()->toIso8601String(), ...$clock]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function history(User $user): array
    {
        return AggregateRow::fetch(
            AssessmentAssignment::query()->where('assessor_user_id', $user->id)->where('status', AssignmentStatus::Completed),
            'count(*) as completed, round(cast(avg(extract(epoch from (ended_at - created_at))) / 86400 as numeric), 1) as average_days',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dueSoon(User $user): array
    {
        $now = Date::now();

        return array_values($this->assigned($user)
            ->with('entity:id,name')
            ->orderByRaw('due_at asc nulls last')
            ->orderBy('id')
            ->limit(self::DUE_SOON)
            ->get(['id', 'request_number', 'entity_id', 'path', 'status', 'due_at'])
            ->map(static fn (Assessment $assessment): array => [
                'id' => $assessment->id,
                'number' => $assessment->request_number,
                ...PathPresenter::of($assessment->path),
                'status' => (string) $assessment->status->value,
                'entity_name' => $assessment->entity->name->toArray(),
                'due_at' => $assessment->due_at?->toIso8601String(),
                'is_overdue' => $assessment->isOverdue($now),
            ])
            ->all());
    }
}

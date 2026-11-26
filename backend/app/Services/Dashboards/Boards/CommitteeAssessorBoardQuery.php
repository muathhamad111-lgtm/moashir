<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\Dashboards\Ratio;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use stdClass;

final readonly class CommitteeAssessorBoardQuery implements DashboardBoardQuery
{
    private const int LATEST_TASKS = 5;

    public function __construct(private AssessmentTotals $assessments) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::CommitteeAssessor;
    }

    public function build(User $user): BoardContent
    {
        $row = AggregateRow::fetch($this->rows($user), 'count(*) as assigned, '
            .'count(*) filter (where status = ?) as completed, '
            .'count(*) filter (where status in (?, ?)) as remaining, '
            .'round(avg(percent) filter (where status = ?), 2) as own_average', [
                CommitteeAssignmentStatus::Completed->value,
                CommitteeAssignmentStatus::NotStarted->value, CommitteeAssignmentStatus::InProgress->value,
                CommitteeAssignmentStatus::Completed->value,
            ]);
        $assigned = ScalarValue::int($row['assigned'] ?? null);
        $completed = ScalarValue::int($row['completed'] ?? null);

        return new BoardContent([
            new DashboardCard('assigned', $assigned, link: Links::COMMITTEE_ASSIGNMENTS),
            new DashboardCard('completed', $completed, link: Links::COMMITTEE_ASSIGNMENTS),
            new DashboardCard('remaining', ScalarValue::int($row['remaining'] ?? null), link: Links::COMMITTEE_ASSIGNMENTS),
            new DashboardCard('progress', Ratio::percent($completed, $assigned), CardFormat::Percent),
            new DashboardCard('own_average', ScalarValue::string($row['own_average'] ?? null) ?: null, CardFormat::Percent),
        ], [
            new DashboardWidget('cycles', $this->cycles($user)),
            new DashboardWidget('latest_tasks', $this->latestTasks($user)),
        ]);
    }

    /**
     * @return Builder<CommitteeAssignment>
     */
    private function rows(User $user): Builder
    {
        return CommitteeAssignment::query()
            ->live()
            ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $user->id))
            ->whereIn('assessment_id', $this->assessments->operational()->select('id'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cycles(User $user): array
    {
        $counts = $this->rows($user)->toBase()
            ->selectRaw('award_cycle_id, count(*) as assigned, count(*) filter (where status = ?) as completed', [CommitteeAssignmentStatus::Completed->value])
            ->groupBy('award_cycle_id')
            ->get()
            ->keyBy(static fn (object $row): int => ScalarValue::int(get_object_vars($row)['award_cycle_id'] ?? null));

        return array_values(CycleCommitteeAssessor::query()
            ->with('awardCycle:id,name,status,committee_start,committee_end')
            ->where('assessor_user_id', $user->id)
            ->active()
            ->whereHas('awardCycle', static fn (Builder $cycle): Builder => $cycle->where('status', '<>', AwardCycleStatus::Closed))
            ->orderBy('id')
            ->get(['id', 'award_cycle_id', 'status'])
            ->map(static fn (CycleCommitteeAssessor $seat): array => self::cycleRow($seat, $counts))
            ->all());
    }

    /**
     * @param  Collection<int, stdClass>  $counts
     * @return array<string, mixed>
     */
    private static function cycleRow(CycleCommitteeAssessor $seat, Collection $counts): array
    {
        $values = get_object_vars($counts->get($seat->award_cycle_id) ?? (object) []);
        $assigned = ScalarValue::int($values['assigned'] ?? null);
        $completed = ScalarValue::int($values['completed'] ?? null);

        return [
            'cycle_id' => $seat->award_cycle_id,
            'name' => $seat->awardCycle->name->toArray(),
            'status' => $seat->awardCycle->status->value,
            'committee_start' => $seat->awardCycle->committee_start->toIso8601String(),
            'committee_end' => $seat->awardCycle->committee_end->toIso8601String(),
            'assigned' => $assigned,
            'completed' => $completed,
            'remaining' => $assigned - $completed,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestTasks(User $user): array
    {
        return array_values($this->rows($user)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_TASKS)
            ->get(['id', 'award_cycle_id', 'status', 'started_at', 'submitted_at', 'updated_at'])
            ->map(static fn (CommitteeAssignment $assignment): array => [
                'assignment_id' => $assignment->id,
                'cycle_id' => $assignment->award_cycle_id,
                'status' => $assignment->status->value,
                'started_at' => $assignment->started_at?->toIso8601String(),
                'submitted_at' => $assignment->submitted_at?->toIso8601String(),
                'updated_at' => $assignment->updated_at->toIso8601String(),
            ])
            ->all());
    }
}

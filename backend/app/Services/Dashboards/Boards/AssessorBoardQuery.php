<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Enums\Assessments\AccreditedAssessmentStatus as Accredited;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use Illuminate\Support\Facades\Date;

/**
 * The assessor's live figures: the open reviews by state, the overdue ones and those due
 * within a week. The period figures and charts come from the assessor overview.
 */
final readonly class AssessorBoardQuery implements DashboardBoardQuery
{
    private const int DUE_SOON_DAYS = 7;

    public function __construct(private AssessmentTotals $assessments) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::Assessor;
    }

    public function build(User $user): BoardContent
    {
        $open = $this->openCounts($user);

        return new BoardContent(array_map(
            static fn (string $key): DashboardCard => new DashboardCard($key, $open[$key] ?? 0, link: Links::ASSIGNED_REQUESTS),
            ['to_start', 'in_progress', 'awaiting_entity', 'overdue', 'due_within_week'],
        ), []);
    }

    /**
     * @return array<string, int>
     */
    private function openCounts(User $user): array
    {
        $clock = [...AssessmentPath::Accredited->reviewClockStateValues(), ...AssessmentPath::Advanced->reviewClockStateValues()];
        $onClock = 'status in ('.AggregateRow::placeholders($clock).')';
        $now = Date::now();

        return AggregateRow::counts($this->assessments->operational()->assignedTo($user->id), [
            'to_start' => ['status = ?', [Accredited::Assigned->value]],
            'in_progress' => ['status in (?, ?)', [Accredited::UnderReview->value, Advanced::UnderInitialAssessment->value]],
            'awaiting_entity' => ['status = ?', [Accredited::NeedsCompletion->value]],
            'overdue' => ['due_at < ? and '.$onClock, [$now->toIso8601String(), ...$clock]],
            'due_within_week' => ['due_at >= ? and due_at < ? and '.$onClock, [$now->toIso8601String(), $now->copy()->addDays(self::DUE_SOON_DAYS)->toIso8601String(), ...$clock]],
        ]);
    }
}

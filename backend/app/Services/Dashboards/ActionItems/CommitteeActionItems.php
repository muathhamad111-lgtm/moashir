<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CommitteeDiscussionStatus;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CommitteeDiscussionEntry;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final readonly class CommitteeActionItems implements ActionItemSource
{
    public function __construct(private AssessmentTotals $assessments) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::AwardCommitteeScore->value);
    }

    public function itemsFor(User $user): array
    {
        return array_values(array_filter([$this->evaluationsItem($user), $this->discussionsItem($user)]));
    }

    private function evaluationsItem(User $user): ?ActionItem
    {
        $row = AggregateRow::fetch(
            CommitteeAssignment::query()
                ->join('award_cycles', 'award_cycles.id', '=', 'committee_assignments.award_cycle_id')
                ->whereIn('committee_assignments.status', [CommitteeAssignmentStatus::NotStarted->value, CommitteeAssignmentStatus::InProgress->value])
                ->whereIn('committee_assignments.cycle_committee_assessor_id', $this->activeSeats($user))
                ->whereIn('award_cycles.status', [AwardCycleStatus::CommitteeAssessment->value, AwardCycleStatus::AwaitingSecretary->value])
                ->whereIn('committee_assignments.assessment_id', $this->assessments->operational()->select('assessments.id')),
            'count(*) as pending, min(award_cycles.committee_end) as committee_end',
        );
        $pending = ScalarValue::int($row['pending'] ?? null);
        $end = $row['committee_end'] ?? null;

        return $pending > 0
            ? new ActionItem('complete_committee_evaluations', $pending, ActionSeverity::Warning, Links::COMMITTEE_ASSIGNMENTS, is_string($end) ? Date::parse($end) : null)
            : null;
    }

    private function discussionsItem(User $user): ?ActionItem
    {
        $seats = $this->activeSeats($user);
        $open = CommitteeDiscussion::query()
            ->where('status', CommitteeDiscussionStatus::Open)
            ->whereIn('award_cycle_id', CycleCommitteeAssessor::query()->select('award_cycle_id')->whereIn('id', $seats))
            ->where(static fn (Builder $deadline): Builder => $deadline->whereNull('deadline_at')->orWhere('deadline_at', '>', Date::now()))
            ->whereNotIn('id', CommitteeDiscussionEntry::query()->select('committee_discussion_id')->whereNull('superseded_at')->whereIn('cycle_committee_assessor_id', $seats))
            ->whereIn('assessment_id', $this->assessments->operational()->select('assessments.id'))
            ->count();

        return $open > 0 ? new ActionItem('answer_committee_discussions', $open, ActionSeverity::Warning, Links::COMMITTEE_DISCUSSIONS) : null;
    }

    /**
     * @return Builder<CycleCommitteeAssessor>
     */
    private function activeSeats(User $user): Builder
    {
        return CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $user->id)->active()
            ->whereIn('award_cycle_id', AwardCycle::query()->select('id')->where('status', '<>', AwardCycleStatus::Closed));
    }
}

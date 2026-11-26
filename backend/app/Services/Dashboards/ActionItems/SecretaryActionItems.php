<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Dashboards\MemberCycleRequests;
use App\Support\Dashboards\DashboardLinks as Links;
use Illuminate\Database\Eloquent\Builder;

/**
 * What waits for an Award Secretary in their own cycles: committee results, substitute evaluations, open
 * discussions, results to rank, the national approval and the announcement (award UX phase 1 adds the last four).
 */
final readonly class SecretaryActionItems implements ActionItemSource
{
    public function __construct(private MemberCycleRequests $requests) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::AwardSecretaryDecide->value);
    }

    public function itemsFor(User $user): array
    {
        $requests = $this->requests->of($user, CycleMemberRole::AwardSecretary);

        return array_values(array_filter([
            $this->item('approve_committee_results', $this->withStatus($requests, Advanced::AwaitingSecretaryApproval), ActionSeverity::Warning, Links::SECRETARY),
            $this->item('approve_substitute_evaluations', $this->pendingSubstitutes($requests), ActionSeverity::Info, Links::SECRETARY),
            $this->item('close_committee_discussions', $this->withStatus($requests, Advanced::CommitteeDiscussion), ActionSeverity::Info, Links::awardListWithStatus(Links::SECRETARY, Advanced::CommitteeDiscussion->value)),
            $this->item('rank_approved_results', $this->withStatus($requests, Advanced::Approved), ActionSeverity::Info, Links::RESULTS),
            $this->item('approve_national_results', $this->withStatus($requests, Advanced::QualifiedForNational), ActionSeverity::Info, Links::RESULTS),
            $this->item('announce_results', $this->cyclesAwaitingAnnouncement($user), ActionSeverity::Warning, Links::RESULTS),
        ]));
    }

    private function item(string $key, int $count, ActionSeverity $severity, string $link): ?ActionItem
    {
        return $count > 0 ? new ActionItem($key, $count, $severity, $link) : null;
    }

    /**
     * @param  Builder<Assessment>  $requests
     */
    private function withStatus(Builder $requests, Advanced $status): int
    {
        return (clone $requests)->where('assessments.status', $status->value)->count();
    }

    /**
     * @param  Builder<Assessment>  $requests
     */
    private function pendingSubstitutes(Builder $requests): int
    {
        return CommitteeAssignment::query()
            ->where('status', CommitteeAssignmentStatus::Completed)
            ->whereNotNull('replaces_assignment_id')
            ->whereNull('substitute_approved_at')
            ->whereIn('assessment_id', (clone $requests)->select('assessments.id'))
            ->count();
    }

    // The national result is approved and the cycle waits for the secretary's manual announcement.
    private function cyclesAwaitingAnnouncement(User $user): int
    {
        return AwardCycle::query()
            ->whereIn('id', $this->requests->cycleIds($user, CycleMemberRole::AwardSecretary))
            ->where('status', AwardCycleStatus::Approved)
            ->count();
    }
}

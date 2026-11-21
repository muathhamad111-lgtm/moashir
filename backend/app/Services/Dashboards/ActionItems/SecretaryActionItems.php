<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Dashboards\MemberCycleRequests;
use App\Support\Dashboards\DashboardLinks as Links;

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
        $awaiting = (clone $requests)->where('assessments.status', Advanced::AwaitingSecretaryApproval->value)->count();
        $substitutes = CommitteeAssignment::query()
            ->where('status', CommitteeAssignmentStatus::Completed)
            ->whereNotNull('replaces_assignment_id')
            ->whereNull('substitute_approved_at')
            ->whereIn('assessment_id', (clone $requests)->select('assessments.id'))
            ->count();

        return array_values(array_filter([
            $awaiting > 0 ? new ActionItem('approve_committee_results', $awaiting, ActionSeverity::Warning, Links::SECRETARY) : null,
            $substitutes > 0 ? new ActionItem('approve_substitute_evaluations', $substitutes, ActionSeverity::Info, Links::SECRETARY) : null,
        ]));
    }
}

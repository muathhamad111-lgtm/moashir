<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Dashboards\MemberCycleRequests;
use App\Support\Dashboards\DashboardLinks as Links;

final readonly class FilteringActionItems implements ActionItemSource
{
    private const array DECIDING_STAGES = [AwardCycleStatus::SubmissionClosed, AwardCycleStatus::CommitteeAssessment, AwardCycleStatus::AwaitingSecretary];

    public function __construct(private MemberCycleRequests $requests) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::AwardFilteringDecide->value);
    }

    public function itemsFor(User $user): array
    {
        $awaiting = $this->requests->of($user, CycleMemberRole::FilteringOfficer)
            ->where('assessments.status', Advanced::AwaitingFiltering->value)
            ->whereIn('assessments.award_cycle_id', AwardCycle::query()->select('id')->whereIn('status', self::DECIDING_STAGES))
            ->count();

        return $awaiting > 0 ? [new ActionItem('decide_filtering', $awaiting, ActionSeverity::Warning, Links::FILTERING)] : [];
    }
}

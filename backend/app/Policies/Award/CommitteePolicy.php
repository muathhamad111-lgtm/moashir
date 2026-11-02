<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Award\CycleRoleScope;
use Illuminate\Auth\Access\Response;

final readonly class CommitteePolicy
{
    private const array MANAGER_GLOBAL_ROLES = [RoleKey::SystemAdmin, RoleKey::RequestFollower];

    private const array MANAGER_MEMBER_ROLES = [CycleMemberRole::FilteringOfficer, CycleMemberRole::AwardSecretary];

    public function __construct(private CycleRoleScope $scope) {}

    public function view(User $actor, AwardCycle $cycle): Response
    {
        return $this->scope->respond($this->manages($actor, $cycle) || $this->reviews($actor, $cycle), $actor, $cycle, PermissionKey::AwardCommitteeManage, PermissionKey::AwardSecretaryReview);
    }

    public function manage(User $actor, AwardCycle $cycle): Response
    {
        return $this->scope->respond($this->manages($actor, $cycle), $actor, $cycle, PermissionKey::AwardCommitteeManage);
    }

    public function manageMember(User $actor, AwardCycle $cycle, CycleCommitteeAssessor $member): Response
    {
        return $member->award_cycle_id === $cycle->id && $member->status->isSeated() ? $this->manage($actor, $cycle) : Response::denyAsNotFound();
    }

    private function manages(User $actor, AwardCycle $cycle): bool
    {
        return $this->scope->allows($actor, $cycle, PermissionKey::AwardCommitteeManage, self::MANAGER_GLOBAL_ROLES, self::MANAGER_MEMBER_ROLES);
    }

    private function reviews(User $actor, AwardCycle $cycle): bool
    {
        return $this->scope->allows($actor, $cycle, PermissionKey::AwardSecretaryReview, [RoleKey::SystemAdmin], [CycleMemberRole::AwardSecretary]);
    }
}

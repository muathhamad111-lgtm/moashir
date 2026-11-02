<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Services\Award\CycleRoleScope;
use Illuminate\Auth\Access\Response;

final readonly class AwardResultsPolicy
{
    public function __construct(private CycleRoleScope $scope) {}

    public function view(User $actor, AwardCycle $cycle): Response
    {
        $reads = $this->scope->allows($actor, $cycle, PermissionKey::AwardResultsView, [RoleKey::SystemAdmin], [CycleMemberRole::AwardSecretary]);

        return $this->scope->respond($reads, $actor, $cycle, PermissionKey::AwardResultsView);
    }

    public function rank(User $actor, AwardCycle $cycle, Assessment $assessment): Response
    {
        if ($assessment->award_cycle_id !== $cycle->id || $assessment->loadMissing('ranking')->ranking === null) {
            return Response::denyAsNotFound();
        }

        return $this->secretaryWith($actor, $cycle, PermissionKey::AwardResultsRank);
    }

    public function approveRegion(User $actor, AwardCycle $cycle, Region $region): Response
    {
        return $cycle->covers($region->id) ? $this->secretaryWith($actor, $cycle, PermissionKey::AwardResultsApprove) : Response::denyAsNotFound();
    }

    public function unapproveRegion(User $actor, AwardCycle $cycle, Region $region): Response
    {
        return $cycle->covers($region->id) ? $this->scope->respond(false, $actor, $cycle) : Response::denyAsNotFound();
    }

    public function approveNational(User $actor, AwardCycle $cycle): Response
    {
        return $this->secretaryWith($actor, $cycle, PermissionKey::AwardResultsApprove);
    }

    public function announce(User $actor, AwardCycle $cycle): Response
    {
        return $this->secretaryWith($actor, $cycle, PermissionKey::AwardResultsAnnounce);
    }

    private function secretaryWith(User $actor, AwardCycle $cycle, PermissionKey $permission): Response
    {
        return $this->scope->respond($this->scope->allows($actor, $cycle, $permission, [], [CycleMemberRole::AwardSecretary]), $actor, $cycle, $permission);
    }
}

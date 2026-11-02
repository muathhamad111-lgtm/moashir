<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CycleRecordScope;
use Illuminate\Auth\Access\Response;

final readonly class AwardCyclePolicy
{
    public function __construct(private CycleRecordScope $scope) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::AwardCyclesView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionKey::AwardCyclesManage->value);
    }

    public function listCandidates(User $actor): bool
    {
        return $actor->can(PermissionKey::AwardCyclesManage->value);
    }

    public function view(User $actor, AwardCycle $cycle): Response
    {
        if (! $actor->can(PermissionKey::AwardCyclesView->value)) {
            return Response::deny();
        }

        return $this->scope->canView($actor, $cycle) ? Response::allow() : Response::denyAsNotFound();
    }

    public function manage(User $actor, AwardCycle $cycle): Response
    {
        if ($this->scope->canManage($actor, $cycle)) {
            return Response::allow();
        }

        return $this->scope->canView($actor, $cycle) ? Response::deny() : Response::denyAsNotFound();
    }

    public function reopen(User $actor, AwardCycle $cycle): Response
    {
        return $this->scope->canView($actor, $cycle) ? Response::deny() : Response::denyAsNotFound();
    }

    public function viewFiltering(User $actor, AwardCycle $cycle): Response
    {
        if ($this->scope->canViewFiltering($actor, $cycle)) {
            return Response::allow();
        }

        if (! $actor->can(PermissionKey::AwardFilteringView->value)) {
            return Response::deny();
        }

        return $this->scope->canView($actor, $cycle) ? Response::deny() : Response::denyAsNotFound();
    }

    public function viewFilteringRequest(User $actor, AwardCycle $cycle, Assessment $assessment): Response
    {
        return $this->isFilteringRequestOf($cycle, $assessment) ? $this->viewFiltering($actor, $cycle) : Response::denyAsNotFound();
    }

    public function decideFiltering(User $actor, AwardCycle $cycle, Assessment $assessment): Response
    {
        if (! $this->isFilteringRequestOf($cycle, $assessment)) {
            return Response::denyAsNotFound();
        }

        if ($this->scope->canDecideFiltering($actor, $cycle)) {
            return Response::allow();
        }

        return $this->viewFiltering($actor, $cycle)->allowed() ? Response::deny() : $this->viewFiltering($actor, $cycle);
    }

    private function isFilteringRequestOf(AwardCycle $cycle, Assessment $assessment): bool
    {
        return $assessment->isAdvanced()
            && $assessment->award_cycle_id === $cycle->id
            && $assessment->status instanceof AdvancedAssessmentStatus
            && $assessment->status->isInFiltering();
    }
}

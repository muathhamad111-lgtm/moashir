<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Enums\Identity\PermissionKey;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class CommitteeAssignmentPolicy
{
    public function viewOwnWork(User $actor): bool
    {
        return $actor->entity_id === null && $actor->can(PermissionKey::AwardCommitteeAssignedView->value);
    }

    public function view(User $actor, CommitteeAssignment $assignment): Response
    {
        if (! $this->holds($actor, $assignment) || $assignment->isVoided()) {
            return Response::denyAsNotFound();
        }

        return $actor->can(PermissionKey::AwardCommitteeAssignedView->value) ? Response::allow() : Response::deny();
    }

    public function work(User $actor, CommitteeAssignment $assignment): Response
    {
        if (! $this->holds($actor, $assignment) || $assignment->isVoided()) {
            return Response::denyAsNotFound();
        }

        return $actor->can(PermissionKey::AwardCommitteeScore->value) ? Response::allow() : Response::deny();
    }

    private function holds(User $actor, CommitteeAssignment $assignment): bool
    {
        return $assignment->member->isHeldBy($actor->id) && $assignment->member->status->isSeated();
    }
}

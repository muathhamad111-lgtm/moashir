<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Enums\Identity\PermissionKey;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class CommitteeDiscussionPolicy
{
    public function respond(User $actor, CommitteeDiscussion $discussion): Response
    {
        $sits = CommitteeAssignment::query()
            ->where('assessment_id', $discussion->assessment_id)
            ->live()
            ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $actor->id)->active())
            ->exists();

        if (! $sits) {
            return Response::denyAsNotFound();
        }

        return $actor->can(PermissionKey::AwardCommitteeScore->value) ? Response::allow() : Response::deny();
    }
}

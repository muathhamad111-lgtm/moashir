<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;

/**
 * The committee discussions on requests the viewer assesses from a seated seat: shared by the
 * list and its per-status counts so both always count the same discussions.
 */
final readonly class MyCommitteeDiscussionsQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<CycleCommitteeAssessor>
     */
    public function seatsOf(User $viewer): Builder
    {
        return CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $viewer->id)->seated();
    }

    /**
     * @return Builder<CommitteeDiscussion>
     */
    public function for(User $viewer): Builder
    {
        return CommitteeDiscussion::query()
            ->whereIn('assessment_id', CommitteeAssignment::query()->select('assessment_id')->live()->whereIn('cycle_committee_assessor_id', $this->seatsOf($viewer)))
            ->whereHas('assessment', fn (Builder $assessment): Builder => $this->freezeGuard->excludeFrozen($assessment));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;

/**
 * The live committee assignments of the viewer's seats, optionally in one cycle: shared by the
 * list and its per-status counts so both always count the same rows.
 */
final readonly class MyCommitteeAssignmentsQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<CommitteeAssignment>
     */
    public function for(User $viewer, ?int $cycleId): Builder
    {
        return CommitteeAssignment::query()
            ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $viewer->id)->seated())
            ->live()
            ->whereHas('assessment', fn (Builder $assessment): Builder => $this->freezeGuard->excludeFrozen($assessment))
            ->when($cycleId !== null, static fn (Builder $query): Builder => $query->where('award_cycle_id', $cycleId));
    }
}

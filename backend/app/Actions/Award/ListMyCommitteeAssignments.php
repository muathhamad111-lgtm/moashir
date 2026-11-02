<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CommitteeAssignmentFilters;
use App\Data\Award\CommitteeWorkPage;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListMyCommitteeAssignments
{
    public function __construct(private EntityFreezeGuard $freezeGuard, private CommitteeVisibilityReader $visibility) {}

    /**
     * @return CommitteeWorkPage<CommitteeAssignment>
     */
    public function handle(User $viewer, CommitteeAssignmentFilters $filters): CommitteeWorkPage
    {
        $page = CommitteeAssignment::query()
            ->with(['assessment.entity.region', 'assessment.entity.city', 'assessment.region', 'awardCycle', 'member'])
            ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $viewer->id)->seated())
            ->live()
            ->whereHas('assessment', fn (Builder $assessment): Builder => $this->freezeGuard->excludeFrozen($assessment))
            ->when($filters->cycleId !== null, static fn (Builder $query): Builder => $query->where('award_cycle_id', $filters->cycleId))
            ->when($filters->status instanceof CommitteeAssignmentStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->orderByRaw("case status when 'in_progress' then 0 when 'not_started' then 1 else 2 end")
            ->orderBy('id')
            ->paginate($filters->perPage);

        return new CommitteeWorkPage($page, $this->visibility->read(), []);
    }
}

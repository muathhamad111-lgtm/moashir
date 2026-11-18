<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CommitteeAssignmentFilters;
use App\Data\Award\CommitteeWorkPage;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Award\CommitteeProgressReader;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Award\MyCommitteeAssignmentsQuery;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListMyCommitteeAssignments
{
    public function __construct(
        private MyCommitteeAssignmentsQuery $assignments,
        private CommitteeVisibilityReader $visibility,
        private CommitteeProgressReader $progress,
    ) {}

    /**
     * @return CommitteeWorkPage<CommitteeAssignment>
     */
    public function handle(User $viewer, CommitteeAssignmentFilters $filters): CommitteeWorkPage
    {
        $page = $this->assignments->for($viewer, $filters->cycleId)
            ->with(['assessment.entity.region', 'assessment.entity.city', 'assessment.entity.owner', 'assessment.region', 'assessment.criteriaVersion', 'awardCycle', 'member'])
            ->when($filters->status instanceof CommitteeAssignmentStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->orderByRaw("case status when 'in_progress' then 0 when 'not_started' then 1 else 2 end")
            ->orderBy('id')
            ->paginate($filters->perPage);

        return new CommitteeWorkPage($page, $this->visibility->read(), [], $this->progress->readMany(array_values($page->items())));
    }
}

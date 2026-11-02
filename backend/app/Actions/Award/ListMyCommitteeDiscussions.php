<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CommitteeWorkPage;
use App\Enums\Award\CommitteeDiscussionStatus;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListMyCommitteeDiscussions
{
    public function __construct(private EntityFreezeGuard $freezeGuard, private CommitteeVisibilityReader $visibility) {}

    /**
     * @return CommitteeWorkPage<CommitteeDiscussion>
     */
    public function handle(User $viewer, ?CommitteeDiscussionStatus $status, int $perPage): CommitteeWorkPage
    {
        $mySeats = CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $viewer->id)->seated();
        $memberIds = array_values((clone $mySeats)->pluck('id')->map(static fn (mixed $id): int => ScalarValue::int($id))->all());

        $page = CommitteeDiscussion::query()
            ->with(['assessment.entity.region', 'assessment.entity.city', 'assessment.region', 'currentEntries'])
            ->whereIn('assessment_id', CommitteeAssignment::query()->select('assessment_id')->live()->whereIn('cycle_committee_assessor_id', $mySeats))
            ->whereHas('assessment', fn (Builder $assessment): Builder => $this->freezeGuard->excludeFrozen($assessment))
            ->when($status instanceof CommitteeDiscussionStatus, static fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);

        return new CommitteeWorkPage($page, $this->visibility->read(), $memberIds);
    }
}

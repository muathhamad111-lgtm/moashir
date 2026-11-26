<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CommitteeWorkPage;
use App\Enums\Award\CommitteeDiscussionStatus;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Identity\User;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Award\MyCommitteeDiscussionsQuery;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListMyCommitteeDiscussions
{
    public function __construct(private MyCommitteeDiscussionsQuery $discussions, private CommitteeVisibilityReader $visibility) {}

    /**
     * @return CommitteeWorkPage<CommitteeDiscussion>
     */
    public function handle(User $viewer, ?CommitteeDiscussionStatus $status, int $perPage): CommitteeWorkPage
    {
        $memberIds = array_values($this->discussions->seatsOf($viewer)->pluck('id')->map(static fn (mixed $id): int => ScalarValue::int($id))->all());

        $page = $this->discussions->for($viewer)
            ->with(['assessment.entity.region', 'assessment.entity.city', 'assessment.entity.owner', 'assessment.region', 'currentEntries'])
            ->when($status instanceof CommitteeDiscussionStatus, static fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);

        return new CommitteeWorkPage($page, $this->visibility->read(), $memberIds);
    }
}

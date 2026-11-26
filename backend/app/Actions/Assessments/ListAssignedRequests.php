<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssignedRequest;
use App\Data\Assessments\AssignedRequestsQuery;
use App\Data\Assessments\ReviewProgress;
use App\Enums\Assessments\AssignedRequestsTab;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssignedRequestsOrdering;
use App\Services\Assessments\ReviewProgressReader;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAssignedRequests
{
    public const array RELATIONS = ['entity.region', 'currentResult', 'openCompletionRequest', 'awardCycle', 'criteriaVersion'];

    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private ReviewProgressReader $progress,
        private AssignedRequestsOrdering $ordering,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AssignedRequest>
     */
    public function handle(User $assessor, AssignedRequestsQuery $request): LengthAwarePaginator
    {
        $query = $this->freezeGuard->excludeFrozen(Assessment::query()
            ->with([...self::RELATIONS, 'assignments' => static fn (HasMany $assignments): HasMany => $assignments->where('assessor_user_id', $assessor->id)])
            ->ofPath($request->path)
            ->reviewedBy($assessor->id));

        if ($request->tab instanceof AssignedRequestsTab) {
            $query->whereIn('status', $request->tab->statusValues());
        }

        $this->ordering->apply($query, $request->order, $assessor->id);
        $page = $query->paginate($request->perPage);
        $progress = $this->progress->readMany(array_values($page->items()));

        return $page->through(static fn (Assessment $assessment): AssignedRequest => new AssignedRequest($assessment, $progress[$assessment->id] ?? new ReviewProgress(0, 0)));
    }
}

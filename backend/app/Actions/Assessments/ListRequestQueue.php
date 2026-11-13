<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\RequestQueueFilters;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\RequestQueueQuery;
use App\Support\Certificates\EntityLevelQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListRequestQueue
{
    public const array SORTABLE = ['submitted_at', '-submitted_at', 'due_at', '-due_at', 'status', '-status', 'created_at', '-created_at'];

    public const array RELATIONS = ['entity.'.EntityLevelQuery::RELATION, 'entity.'.EntityLevelQuery::DISPLAY_RELATION, 'currentResult', 'activeAssignment.assessor', 'openCompletionRequest', 'awardCycle', 'region', 'period'];

    public function __construct(private RequestQueueQuery $queue) {}

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function handle(RequestQueueFilters $filters): LengthAwarePaginator
    {
        $query = $this->queue->filtered($filters)->with(self::RELATIONS);
        $query->orderBy(ltrim($filters->sort, '-'), str_starts_with($filters->sort, '-') ? 'desc' : 'asc');

        return $query->orderByDesc('id')->paginate($filters->perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentListFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentListQuery;
use App\Support\Assessments\StatusMask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAssessments
{
    public const array SORTABLE = ['created_at', '-created_at', 'submitted_at', '-submitted_at', 'completed_at', '-completed_at'];

    public function __construct(private AssessmentListQuery $listQuery) {}

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function handle(User $actor, AssessmentListFilters $filters): LengthAwarePaginator
    {
        $query = $this->listQuery->scoped($actor, $filters)->with(['entity', 'currentResult', 'awardCycle']);
        $this->filterStatus($query, $actor, $filters->status);
        $this->filterOpen($query, $actor, $filters->isOpen);
        $this->filterResult($query, $filters->hasResult);

        $query->orderBy(ltrim($filters->sort, '-'), str_starts_with($filters->sort, '-') ? 'desc' : 'asc');

        return $query->orderByDesc('id')->paginate($filters->perPage);
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    private function filterStatus(Builder $query, User $actor, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        $query->where('status', $status);

        if (in_array($status, StatusMask::hiddenStatusValuesFor($actor), true)) {
            $query->where(static fn (Builder $visible): Builder => $visible
                ->where('path', '<>', AssessmentPath::Advanced)
                ->orWhereHas('awardCycle', static fn (Builder $cycle): Builder => $cycle->whereNotNull('announced_at')));
        }
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    private function filterOpen(Builder $query, User $actor, ?bool $isOpen): void
    {
        $masked = StatusMask::hiddenStatusValuesFor($actor);

        match ($isOpen) {
            true => $query->openForViewer($masked),
            false => $query->whereNot(static fn (Builder $open): Builder => $open->openForViewer($masked)),
            null => null,
        };
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    private function filterResult(Builder $query, ?bool $hasResult): void
    {
        match ($hasResult) {
            true => $query->exposingResult(),
            false => $query->whereNot(static fn (Builder $results): Builder => $results->exposingResult()),
            null => null,
        };
    }
}

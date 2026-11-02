<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CycleListFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CycleListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAwardCycles
{
    public const array SORTABLE = ['submission_start', '-submission_start', 'announcement_at', '-announcement_at', 'created_at', '-created_at'];

    public function __construct(private CycleListQuery $cycles) {}

    /**
     * @return LengthAwarePaginator<int, AwardCycle>
     */
    public function handle(User $viewer, CycleListFilters $filters): LengthAwarePaginator
    {
        $query = $this->cycles->filtered($viewer, $filters)
            ->with('regions')
            ->withCount([
                'requests as requests_count' => static fn (Builder $request): Builder => $request->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::queueStates())),
                'requests as qualified_count' => static fn (Builder $request): Builder => $request->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::qualifiedStates())),
            ]);
        $query->orderBy(ltrim($filters->sort, '-'), str_starts_with($filters->sort, '-') ? 'desc' : 'asc');

        return $query->orderByDesc('id')->paginate($filters->perPage);
    }
}

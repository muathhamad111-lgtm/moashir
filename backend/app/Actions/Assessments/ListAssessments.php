<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentListFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\Assessments\StatusMask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAssessments
{
    public const array SORTABLE = ['created_at', '-created_at', 'submitted_at', '-submitted_at', 'completed_at', '-completed_at'];

    public function __construct(private AssessmentRecordScope $scope, private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function handle(User $actor, AssessmentListFilters $filters): LengthAwarePaginator
    {
        $query = Assessment::query()->with(['entity', 'currentResult', 'awardCycle']);
        $this->scope->restrict($query, $actor);

        if ($filters->entityId === null) {
            $this->freezeGuard->excludeFrozen($query);
        } else {
            $query->ownedBy($filters->entityId);
        }

        if ($filters->path instanceof AssessmentPath) {
            $query->ofPath($filters->path);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->status !== null && in_array($filters->status, StatusMask::hiddenStatusValuesFor($actor), true)) {
            $query->where(static fn (Builder $visible): Builder => $visible
                ->where('path', '<>', AssessmentPath::Advanced)
                ->orWhereHas('awardCycle', static fn (Builder $cycle): Builder => $cycle->whereNotNull('announced_at')));
        }

        $query->orderBy(ltrim($filters->sort, '-'), str_starts_with($filters->sort, '-') ? 'desc' : 'asc');

        return $query->orderByDesc('id')->paginate($filters->perPage);
    }
}

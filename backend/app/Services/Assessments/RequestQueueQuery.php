<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\RequestQueueFilters;
use App\Data\Periods\PeriodFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Periods\PeriodScopes;
use App\Support\ValueObjects\SearchTerm;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final readonly class RequestQueueQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard, private PeriodScopes $periods) {}

    /**
     * @return Builder<Assessment>
     */
    public function filtered(RequestQueueFilters $filters): Builder
    {
        $query = $this->queued([$filters->path]);

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->cycleId !== null) {
            $query->where('award_cycle_id', $filters->cycleId);
        }

        if ($filters->overdue !== null) {
            $filters->overdue ? $this->overdue($query) : $query->whereNot(fn (Builder $inner): Builder => $this->overdue($inner));
        }

        if ($filters->assessorId !== null) {
            $query->assignedTo($filters->assessorId);
        }

        if ($filters->search instanceof SearchTerm) {
            $query->matchingSearch($filters->search);
        }

        if ($filters->period instanceof PeriodFilter) {
            $this->periods->submittedIn($query, $filters->period);
        }

        return $query;
    }

    /**
     * @param  list<AssessmentPath>  $paths
     * @return Builder<Assessment>
     */
    public function queued(array $paths): Builder
    {
        return $this->inStates($paths, static fn (AssessmentPath $path): array => $path->queueStateValues());
    }

    /**
     * @param  list<AssessmentPath>  $paths
     * @return Builder<Assessment>
     */
    public function preparing(array $paths): Builder
    {
        return $this->inStates($paths, static fn (AssessmentPath $path): array => $path->preparingStateValues());
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return Builder<Assessment>
     */
    public function overdue(Builder $query): Builder
    {
        return $query
            ->where(static function (Builder $clock): void {
                foreach (AssessmentPath::reviewed() as $path) {
                    $clock->orWhere(static fn (Builder $byPath): Builder => $byPath->ofPath($path)->whereIn('status', $path->reviewClockStateValues()));
                }
            })
            ->where('due_at', '<', Date::now());
    }

    /**
     * @param  list<AssessmentPath>  $paths
     * @param  Closure(AssessmentPath): list<string>  $statesOf
     * @return Builder<Assessment>
     */
    private function inStates(array $paths, Closure $statesOf): Builder
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query()->where(static function (Builder $inStates) use ($paths, $statesOf): void {
            foreach ($paths as $path) {
                $inStates->orWhere(static fn (Builder $byPath): Builder => $byPath->ofPath($path)->whereIn('status', $statesOf($path)));
            }

            if ($paths === []) {
                $inStates->whereRaw('false');
            }
        }));
    }
}

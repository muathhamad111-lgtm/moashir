<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\RequestQueueFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final readonly class RequestQueueQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<Assessment>
     */
    public function filtered(RequestQueueFilters $filters): Builder
    {
        $query = $this->freezeGuard->excludeFrozen(Assessment::query()->where(static function (Builder $queued) use ($filters): void {
            foreach ($filters->paths() as $path) {
                $queued->orWhere(static fn (Builder $byPath): Builder => $byPath->ofPath($path)->whereIn('status', $path->queueStateValues()));
            }
        }));

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

        return $query;
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
}

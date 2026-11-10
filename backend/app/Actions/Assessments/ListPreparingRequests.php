<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\PreparingRequest;
use App\Data\Assessments\PreparingRequestFilters;
use App\Data\Periods\PeriodFilter;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\RequestQueueQuery;
use App\Services\Assessments\SubmissionProgressReader;
use App\Services\Periods\PeriodLocator;
use App\Services\Periods\PeriodScopes;
use App\Support\Certificates\EntityLevelQuery;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListPreparingRequests
{
    public const array SORTABLE = ['created_at', '-created_at'];

    private const array RELATIONS = ['entity.'.EntityLevelQuery::RELATION, 'payment.receipt', 'criteriaVersion', 'awardCycle', 'region'];

    public function __construct(
        private RequestQueueQuery $queue,
        private SubmissionProgressReader $progress,
        private PeriodScopes $periods,
        private PeriodLocator $locator,
    ) {}

    /**
     * @return LengthAwarePaginator<int, PreparingRequest>
     */
    public function handle(PreparingRequestFilters $filters): LengthAwarePaginator
    {
        $query = $this->queue->preparing([$filters->path])->withEntityActivity()->with(self::RELATIONS);

        if ($filters->cycleId !== null) {
            $query->where('award_cycle_id', $filters->cycleId);
        }

        if ($filters->search instanceof SearchTerm) {
            $query->matchingSearch($filters->search);
        }

        if ($filters->period instanceof PeriodFilter) {
            $this->periods->openedIn($query, $filters->period);
        }

        $page = $query
            ->orderBy('created_at', str_starts_with($filters->sort, '-') ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
        $progress = $this->progress->readMany(array_values($page->items()));
        $periodEndsOn = $this->locator->current()?->ends_on;

        return $page->through(static fn (Assessment $assessment): PreparingRequest => new PreparingRequest($assessment, $progress[$assessment->id] ?? null, $periodEndsOn));
    }
}

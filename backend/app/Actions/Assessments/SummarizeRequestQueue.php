<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\RequestQueueFilters;
use App\Enums\Assessments\QueueStage;
use App\Services\Assessments\RequestQueueQuery;
use App\Support\ScalarValue;

final readonly class SummarizeRequestQueue
{
    public function __construct(private RequestQueueQuery $queue) {}

    /**
     * Counts over every stage, so the stage cards keep their numbers whichever stage is shown.
     *
     * @return array{total: int, by_status: array<string, int>, by_stage: array<string, int>, overdue: int}
     */
    public function handle(RequestQueueFilters $filters): array
    {
        $everyStage = $filters->acrossStages();
        $counts = $this->queue->filtered($everyStage)->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $byStatus = [];

        foreach (QueueStage::All->stateValuesOf($filters->path) as $status) {
            $byStatus[$status] = ScalarValue::int($counts->get($status));
        }

        $overdue = $this->queue->overdue($this->queue->filtered($everyStage))->count();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'by_stage' => [...$this->byStage($filters, $byStatus), QueueStage::Overdue->value => $overdue],
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  array<string, int>  $byStatus
     * @return array<string, int>
     */
    private function byStage(RequestQueueFilters $filters, array $byStatus): array
    {
        $byStage = [];

        foreach (QueueStage::countedByState() as $stage) {
            $byStage[$stage->value] = array_sum(array_intersect_key($byStatus, array_flip($stage->stateValuesOf($filters->path))));
        }

        return $byStage;
    }
}

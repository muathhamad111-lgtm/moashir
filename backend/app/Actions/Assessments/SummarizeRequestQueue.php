<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\RequestQueueFilters;
use App\Services\Assessments\RequestQueueQuery;
use App\Support\ScalarValue;

final readonly class SummarizeRequestQueue
{
    public function __construct(private RequestQueueQuery $queue) {}

    /**
     * @return array{total: int, by_status: array<string, int>, overdue: int}
     */
    public function handle(RequestQueueFilters $filters): array
    {
        $counts = $this->queue->filtered($filters)->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $byStatus = [];

        foreach ($filters->path->queueStateValues() as $status) {
            $byStatus[$status] = ScalarValue::int($counts->get($status));
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'overdue' => $this->queue->overdue($this->queue->filtered($filters))->count(),
        ];
    }
}

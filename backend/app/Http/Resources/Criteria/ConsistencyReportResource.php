<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Data\Criteria\ConsistencyProblem;
use App\Data\Criteria\ConsistencyReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ConsistencyReport $resource
 */
final class ConsistencyReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'consistent' => $this->resource->isConsistent(),
            'problems' => array_map(static fn (ConsistencyProblem $problem): array => [
                ...$problem->toArray(),
                'message' => $problem->message(),
            ], $this->resource->problems),
        ];
    }
}

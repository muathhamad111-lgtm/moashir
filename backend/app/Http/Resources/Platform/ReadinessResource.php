<?php

declare(strict_types=1);

namespace App\Http\Resources\Platform;

use App\Data\Platform\ReadinessReport;
use App\Data\Platform\ReadinessResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ReadinessReport $resource
 */
final class ReadinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = ['status' => $this->resource->status()->value];

        if (! $this->resource->isDetailed) {
            return $summary;
        }

        return [
            ...$summary,
            'checks' => array_map(static fn (ReadinessResult $result): array => [
                'name' => $result->name,
                'critical' => $result->isCritical,
                'status' => $result->status->value,
            ], $this->resource->results),
            'checked_at' => $this->resource->checkedAt->toIso8601String(),
        ];
    }
}

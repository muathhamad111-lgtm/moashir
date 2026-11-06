<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboards;

use App\Data\Dashboards\ActionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ActionItem $resource
 */
final class ActionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'count' => $this->resource->count,
            'severity' => $this->resource->severity->value,
            'link' => $this->resource->link,
            'due_at' => $this->resource->dueAt?->toIso8601String(),
            'context' => $this->resource->context?->toArray(),
        ];
    }
}

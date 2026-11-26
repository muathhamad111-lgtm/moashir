<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Data\Entities\EntityTimelineEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EntityTimelineEntry $resource
 */
final class EntityTimelineEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => "{$entry->kind->value}:{$entry->sourceId}",
            'kind' => $entry->kind->value,
            'occurred_at' => $entry->occurredAt->toIso8601String(),
            'actor' => $entry->actor?->toArray(),
            'status' => $entry->status,
            'reason' => $entry->reason,
            'fields' => $entry->fields,
            'change' => $entry->change === null ? null : new StaffProfileChangeResource($entry->change)->toArray($request),
            'email' => $entry->email,
        ];
    }
}

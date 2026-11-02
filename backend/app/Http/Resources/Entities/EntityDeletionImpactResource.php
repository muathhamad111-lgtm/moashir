<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Data\Entities\EntityDeletionImpact;
use App\Data\Entities\OpenEntityRequest;
use App\Enums\Entities\ErasureRetainedRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EntityDeletionImpact $resource
 */
final class EntityDeletionImpactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'open_requests' => array_map(static fn (OpenEntityRequest $open): array => $open->toArray(), $this->resource->openRequests),
            'open_requests_count' => count($this->resource->openRequests),
            'requires_confirmation' => $this->resource->hasOpenRequests(),
            'kept_on_permanent_deletion' => array_map(
                static fn (ErasureRetainedRecord $record): string => $record->value,
                $this->resource->keptOnPermanentDeletion,
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\CriteriaVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CriteriaVersion $resource
 */
final class CriteriaVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'version_number' => $this->resource->version_number,
            'label' => $this->resource->label?->toArray(),
            'status' => $this->resource->status->value,
            'scale_kind' => $this->resource->scale_kind?->value,
            'consistent' => $this->resource->isConsistent(),
            'cloned_from_version_id' => $this->resource->cloned_from_version_id,
            'published_at' => $this->resource->published_at?->toIso8601String(),
            'archived_at' => $this->resource->archived_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
            'updated_at' => $this->resource->updated_at->toIso8601String(),
        ];
    }
}

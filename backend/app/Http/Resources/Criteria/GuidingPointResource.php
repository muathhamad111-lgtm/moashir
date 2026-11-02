<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\GuidingPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read GuidingPoint $resource
 */
final class GuidingPointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'sub_criterion_id' => $this->resource->sub_criterion_id,
            'code' => $this->resource->code,
            'description' => $this->resource->description->toArray(),
            'weight' => $this->resource->weight->toDecimal(),
            'active' => $this->resource->is_active,
            'order' => $this->resource->sort_order,
            'attachments' => AttachmentRequirementResource::collection($this->resource->attachmentRequirements)->resolve($request),
        ];
    }
}

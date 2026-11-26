<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\AttachmentRequirement;
use App\Support\Criteria\AttachmentRequirementShape;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AttachmentRequirement $resource
 */
final class AttachmentRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            ...AttachmentRequirementShape::of($this->resource),
            'order' => $this->resource->sort_order,
        ];
    }
}

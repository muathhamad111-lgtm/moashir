<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\AttachmentRequirement;
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
            'key' => $this->resource->key,
            'name' => $this->resource->name->toArray(),
            'required' => $this->resource->is_required,
            'order' => $this->resource->sort_order,
        ];
    }
}

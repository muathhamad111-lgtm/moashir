<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Data\Entities\StaffProfileChangeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StaffProfileChangeEntry $resource
 */
final class StaffProfileChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new ProfileChangeResource($this->resource->entry)->toArray($request),
            'changed_by_staff' => $this->resource->author?->toArray(),
        ];
    }
}

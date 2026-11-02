<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read City $resource
 */
final class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'region_id' => $this->resource->region_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name->toArray(),
            'sort_order' => $this->resource->sort_order,
            'is_active' => $this->resource->is_active,
        ];
    }
}

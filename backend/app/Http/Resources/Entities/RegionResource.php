<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\City;
use App\Models\Entities\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Region $resource
 */
final class RegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name->toArray(),
            'cities' => $this->resource->cities->map(static fn (City $city): array => [
                'id' => $city->id,
                'code' => $city->code,
                'name' => $city->name->toArray(),
            ])->values()->all(),
        ];
    }
}

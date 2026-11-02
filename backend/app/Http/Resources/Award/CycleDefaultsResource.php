<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\CycleDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CycleDefaults $resource
 */
final class CycleDefaultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}

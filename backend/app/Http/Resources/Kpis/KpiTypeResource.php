<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\KpiType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read KpiType $resource
 */
final class KpiTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource;

        return [
            'id' => $type->id,
            'key' => $type->key,
            'name' => $type->name->toArray(),
            'order' => $type->sort_order,
            'active' => $type->is_active,
        ];
    }
}

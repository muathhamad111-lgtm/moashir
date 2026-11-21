<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\KpiValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read KpiValue $resource
 */
final class KpiValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value->value,
            'measured_on' => $this->resource->measured_on->toDateString(),
        ];
    }
}

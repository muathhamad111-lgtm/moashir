<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\Kpi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Kpi $resource
 */
final class KpiOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kpi = $this->resource;

        return [
            'id' => $kpi->id,
            'slug' => $kpi->slug,
            'name' => $kpi->name->toArray(),
            'published' => $kpi->is_published,
            'order' => $kpi->sort_order,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\Kpi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Kpi $resource
 */
final class KpiPublicDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kpi = $this->resource;
        $name = $kpi->name->withFallback();

        return [
            ...KpiPublicResource::fieldsOf($kpi),
            'full_description' => $kpi->full_description?->withFallback(),
            'notes' => $kpi->notes?->withFallback(),
            'history' => KpiValueResource::collection($kpi->values)->resolve($request),
            'seo' => [
                'title' => $name,
                'description' => $kpi->short_description?->withFallback(),
            ],
        ];
    }
}

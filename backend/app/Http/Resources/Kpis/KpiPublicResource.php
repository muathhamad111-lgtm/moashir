<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\Kpi;
use App\Support\Files\PublicAssetLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Kpi $resource
 */
final class KpiPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fieldsOf($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fieldsOf(Kpi $kpi): array
    {
        return [
            'id' => $kpi->id,
            'slug' => $kpi->slug,
            'name' => $kpi->name->withFallback(),
            'short_description' => $kpi->short_description?->withFallback(),
            'value' => $kpi->value?->value,
            'target' => $kpi->target?->value,
            'baseline' => $kpi->baseline?->value,
            'unit' => $kpi->unit?->withFallback(),
            'category' => $kpi->category === null ? null : ['id' => $kpi->category->id, 'key' => $kpi->category->key, 'name' => $kpi->category->name->withFallback()],
            'type' => $kpi->type === null ? null : ['id' => $kpi->type->id, 'key' => $kpi->type->key, 'name' => $kpi->type->name->withFallback()],
            'status' => $kpi->status?->value,
            'owner' => $kpi->owner?->withFallback(),
            'data_source' => $kpi->data_source?->withFallback(),
            'calc_method' => $kpi->calc_method?->withFallback(),
            'measurement_year' => $kpi->measurement_year,
            'last_update' => $kpi->value_updated_at?->toIso8601String(),
            'external_link' => $kpi->external_link,
            'icon' => $kpi->icon,
            'image' => PublicAssetLink::of($kpi->image),
            'card_color' => $kpi->card_color_token?->value,
            'badge_color' => $kpi->badge_color_token?->value,
            'order' => $kpi->sort_order,
            'created_at' => $kpi->created_at->toIso8601String(),
        ];
    }
}

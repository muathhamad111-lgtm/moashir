<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\Kpi;
use App\Support\Files\PublicAssetLink;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Kpi $resource
 */
final class KpiResource extends JsonResource
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
            'short_description' => $this->text($kpi->short_description),
            'full_description' => $this->text($kpi->full_description),
            'value' => $kpi->value?->value,
            'target' => $kpi->target?->value,
            'baseline' => $kpi->baseline?->value,
            'unit' => $this->text($kpi->unit),
            'kpi_category_id' => $kpi->kpi_category_id,
            'kpi_type_id' => $kpi->kpi_type_id,
            'status' => $kpi->status?->value,
            'owner' => $this->text($kpi->owner),
            'data_source' => $this->text($kpi->data_source),
            'calc_method' => $this->text($kpi->calc_method),
            'notes' => $this->text($kpi->notes),
            'measurement_year' => $kpi->measurement_year,
            'last_update' => $kpi->value_updated_at?->toIso8601String(),
            'external_link' => $kpi->external_link,
            'icon' => $kpi->icon,
            'image' => PublicAssetLink::of($kpi->image),
            'card_color_token' => $kpi->card_color_token?->value,
            'badge_color_token' => $kpi->badge_color_token?->value,
            'show_on_homepage' => $kpi->show_on_homepage,
            'published' => $kpi->is_published,
            'published_at' => $kpi->published_at?->toIso8601String(),
            'order' => $kpi->sort_order,
            'updated_by' => $kpi->updatedBy === null ? null : ['id' => $kpi->updatedBy->id, 'name' => $kpi->updatedBy->isSuperAdmin() ? null : $kpi->updatedBy->name],
            'created_at' => $kpi->created_at->toIso8601String(),
            'updated_at' => $kpi->updated_at->toIso8601String(),
        ];
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private function text(?LocalizedContent $text): ?array
    {
        return $text?->toArray();
    }
}

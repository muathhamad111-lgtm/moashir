<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Models\Kpis\Kpi;

final class KpiAuditSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function of(Kpi $kpi): array
    {
        return [
            'slug' => $kpi->slug,
            'name' => $kpi->name->toArray(),
            'category_id' => $kpi->kpi_category_id,
            'type_id' => $kpi->kpi_type_id,
            'value' => $kpi->value?->value,
            'target' => $kpi->target?->value,
            'baseline' => $kpi->baseline?->value,
            'unit' => $kpi->unit?->toArray(),
            'measurement_year' => $kpi->measurement_year,
            'status' => $kpi->status?->value,
            'owner' => $kpi->owner?->toArray(),
            'external_link' => $kpi->external_link,
            'icon' => $kpi->icon,
            'image_file_id' => $kpi->image_file_id,
            'card_color_token' => $kpi->card_color_token?->value,
            'badge_color_token' => $kpi->badge_color_token?->value,
            'show_on_homepage' => $kpi->show_on_homepage,
            'published' => $kpi->is_published,
            'order' => $kpi->sort_order,
        ];
    }
}

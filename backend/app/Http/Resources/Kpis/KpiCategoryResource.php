<?php

declare(strict_types=1);

namespace App\Http\Resources\Kpis;

use App\Models\Kpis\KpiCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read KpiCategory $resource
 */
final class KpiCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->resource;

        return [
            'id' => $category->id,
            'key' => $category->key,
            'name' => $category->name->toArray(),
            'description' => $category->description?->toArray(),
            'icon' => $category->icon,
            'order' => $category->sort_order,
            'active' => $category->is_active,
        ];
    }
}

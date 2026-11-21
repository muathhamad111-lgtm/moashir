<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\CmsSectionItem;
use App\Support\Files\PublicAssetLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CmsSectionItem $resource
 */
final class CmsItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id' => $item->id,
            'section_id' => $item->cms_section_id,
            'enabled' => $item->is_enabled,
            'order' => $item->sort_order,
            'title' => $item->title?->toArray(),
            'subtitle' => $item->subtitle?->toArray(),
            'description' => $item->description?->toArray(),
            'cta_label' => $item->cta_label?->toArray(),
            'cta_url' => $item->cta_url,
            'icon' => $item->icon,
            'image' => PublicAssetLink::of($item->image),
            'meta' => $item->meta,
            'updated_at' => $item->updated_at->toIso8601String(),
        ];
    }
}

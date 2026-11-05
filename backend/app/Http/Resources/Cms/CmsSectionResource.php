<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\CmsSection;
use App\Support\Files\PublicAssetLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CmsSection $resource
 */
final class CmsSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $section = $this->resource;

        return [
            'id' => $section->id,
            'page' => $section->page->key->value,
            'key' => $section->key,
            'type' => $section->type->value,
            'enabled' => $section->is_enabled,
            'order' => $section->sort_order,
            'title' => $section->title?->toArray(),
            'subtitle' => $section->subtitle?->toArray(),
            'description' => $section->description?->toArray(),
            'cta_label' => $section->cta_label?->toArray(),
            'cta_url' => $section->cta_url,
            'icon' => $section->icon,
            'background' => $section->background,
            'image' => PublicAssetLink::of($section->image),
            'meta' => $section->meta,
            'items' => CmsItemResource::collection($section->items)->resolve($request),
            'updated_at' => $section->updated_at->toIso8601String(),
        ];
    }
}

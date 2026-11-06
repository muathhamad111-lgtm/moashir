<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CmsPage $resource
 */
final class CmsPageSummaryResource extends JsonResource
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
    public static function fieldsOf(CmsPage $page): array
    {
        return [
            'key' => $page->key->value,
            'title' => $page->title?->toArray(),
            'published' => $page->is_published,
            'published_at' => $page->published_at?->toIso8601String(),
            'has_unpublished_changes' => $page->hasUnpublishedChanges(),
            'content_updated_at' => $page->content_updated_at?->toIso8601String(),
        ];
    }
}

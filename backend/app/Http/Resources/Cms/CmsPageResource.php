<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CmsPage $resource
 */
final class CmsPageResource extends JsonResource
{
    /**
     * @param  Collection<int, CmsSection>|null  $sections
     */
    public function __construct(CmsPage $page, private readonly ?Collection $sections = null)
    {
        parent::__construct($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;

        return [
            ...CmsPageSummaryResource::fieldsOf($page),
            'seo' => [
                'title' => $page->seo['title'] ?? null,
                'description' => $page->seo['description'] ?? null,
            ],
            'sections' => $this->sections instanceof Collection ? CmsSectionResource::collection($this->sections)->resolve($request) : null,
        ];
    }
}

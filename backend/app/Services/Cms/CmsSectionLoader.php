<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use Illuminate\Database\Eloquent\Collection;

final class CmsSectionLoader
{
    public function load(int $sectionId): CmsSection
    {
        return CmsSection::query()->with(['page', 'image', 'items.image'])->findOrFail($sectionId);
    }

    /**
     * @return Collection<int, CmsSection>
     */
    public function ofPage(CmsPage $page): Collection
    {
        return $page->sections()->with(['page', 'image', 'items.image'])->get();
    }
}

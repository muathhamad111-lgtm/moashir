<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileRemover;
use Illuminate\Database\Eloquent\Builder;

final readonly class CmsImageJanitor
{
    public function __construct(private StoredFileRemover $remover) {}

    public function removeUnreferenced(CmsPage $page): int
    {
        $sectionIds = CmsSection::withTrashed()->where('cms_page_id', $page->id)->pluck('id')->all();
        $itemIds = CmsSectionItem::withTrashed()->whereIn('cms_section_id', $sectionIds)->pluck('id')->all();

        $unreferenced = StoredFile::query()
            ->ofPurpose(StoredFilePurpose::CmsImage)
            ->where(static fn (Builder $owner): Builder => $owner
                ->where(static fn (Builder $section): Builder => $section->where('attachable_type', new CmsSection()->getMorphClass())->whereIn('attachable_id', $sectionIds))
                ->orWhere(static fn (Builder $item): Builder => $item->where('attachable_type', new CmsSectionItem()->getMorphClass())->whereIn('attachable_id', $itemIds)))
            ->whereNotIn('id', CmsSection::query()->where('cms_page_id', $page->id)->whereNotNull('image_file_id')->select('image_file_id'))
            ->whereNotIn('id', CmsSectionItem::query()->whereIn('cms_section_id', $sectionIds)->whereNotNull('image_file_id')->select('image_file_id'))
            ->get();

        foreach ($unreferenced as $file) {
            $this->remover->remove($file);
        }

        return $unreferenced->count();
    }
}

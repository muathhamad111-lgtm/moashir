<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Data\Cms\LegalDocumentText;
use App\Enums\Cms\CmsSectionType;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class LegalPageFiller
{
    public function __construct(private LegalPageSections $sections) {}

    public function fill(CmsPage $page, LegalDocumentText $document, ?User $editor): int
    {
        $page->fill(['title' => $document->title->toArray()]);
        $page->forceFill(['content_updated_at' => Date::now(), 'has_draft_changes' => true, 'updated_by_user_id' => $editor?->id])->save();
        $sections = $this->sections->of($document->body);

        foreach ($sections as $position => $section) {
            $model = new CmsSection;
            $model->fill(['key' => $section->key, 'type' => CmsSectionType::RichText, 'title' => $section->title, 'description' => $section->description]);
            $model->forceFill(['cms_page_id' => $page->id, 'sort_order' => $position + 1, 'updated_by_user_id' => $editor?->id])->save();
        }

        return count($sections);
    }
}

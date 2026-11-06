<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Cms\PublishCmsPage;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\LegalDocument;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Services\Cms\CmsWriteScope;
use App\Services\Cms\LegalDocumentSource;
use App\Services\Cms\LegalPageFiller;

final readonly class DemoLegalPageInstaller
{
    public function __construct(
        private LegalDocumentSource $source,
        private LegalPageFiller $filler,
        private CmsWriteScope $scope,
        private PublishCmsPage $publish,
    ) {}

    public function install(User $editor): int
    {
        $page = CmsPage::query()->where('key', CmsPageKey::Accessibility)->firstOrFail();

        if ($page->is_published) {
            return 0;
        }

        $this->scope->locked($page->id, function (CmsPage $current) use ($editor): void {
            if (! $current->sections()->exists()) {
                $this->filler->fill($current, $this->source->read(LegalDocument::AccessibilityStatement), $editor);
            }
        });
        $this->publish->handle($editor, $page);

        return 1;
    }
}

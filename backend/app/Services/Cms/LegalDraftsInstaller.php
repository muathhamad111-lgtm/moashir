<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Actions\Cms\CreateLegalDraft;
use App\Data\Cms\LegalDraft;
use App\Data\Cms\LegalDraftsInstallation;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\LegalDocument;
use App\Enums\Identity\TermsKind;
use App\Models\Cms\CmsPage;
use App\Models\Identity\TermsVersion;

final readonly class LegalDraftsInstaller
{
    public function __construct(
        private LegalDocumentSource $source,
        private CreateLegalDraft $createDraft,
        private LegalPageFiller $filler,
        private CmsWriteScope $scope,
    ) {}

    public function install(): LegalDraftsInstallation
    {
        $textDrafts = 0;

        foreach (TermsKind::cases() as $kind) {
            $textDrafts += $this->storeTextDraft($kind) ? 1 : 0;
        }

        return new LegalDraftsInstallation($textDrafts, $this->storeAccessibilityDraft() ? 1 : 0);
    }

    private function storeTextDraft(TermsKind $kind): bool
    {
        $isTaken = TermsVersion::query()->ofKind($kind)
            ->where(static fn ($query) => $query->where('version', LegalDocumentSource::SEED_VERSION)->orWhereNull('published_at'))
            ->exists();

        if ($isTaken) {
            return false;
        }

        $text = $this->source->read(LegalDocument::of($kind));
        $this->createDraft->handle(new LegalDraft($kind, LegalDocumentSource::SEED_VERSION, $text->title, $text->body));

        return true;
    }

    private function storeAccessibilityDraft(): bool
    {
        $page = CmsPage::query()->where('key', CmsPageKey::Accessibility)->firstOrFail();

        return $this->scope->locked($page->id, function (CmsPage $current): bool {
            if ($current->sections()->exists()) {
                return false;
            }

            $this->filler->fill($current, $this->source->read(LegalDocument::AccessibilityStatement), null);

            return true;
        });
    }
}

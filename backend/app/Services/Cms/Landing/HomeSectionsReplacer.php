<?php

declare(strict_types=1);

namespace App\Services\Cms\Landing;

use App\Actions\Cms\CreateCmsSection;
use App\Actions\Cms\CreateCmsSectionItem;
use App\Actions\Cms\RemoveCmsSection;
use App\Data\Cms\CmsContentChanges;
use App\Data\Cms\LandingContent;
use App\Data\Cms\NewCmsItem;
use App\Data\Cms\NewCmsSection;
use App\Enums\Cms\CmsSectionType;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Support\Cms\CmsContentDefinition;
use App\Support\ScalarValue;

final readonly class HomeSectionsReplacer
{
    public function __construct(
        private RemoveCmsSection $removeSection,
        private CreateCmsSection $createSection,
        private CreateCmsSectionItem $createItem,
    ) {}

    public function replace(User $editor, CmsPage $page, LandingContent $content): int
    {
        $this->removeCurrent($editor, $page);

        foreach ($content->sections() as $definition) {
            $this->create($editor, $page, $definition);
        }

        return $page->sections()->count();
    }

    private function removeCurrent(User $editor, CmsPage $page): void
    {
        foreach ($page->sections()->get() as $section) {
            CmsSectionItem::query()->whereBelongsTo($section, 'section')->delete();
            $this->removeSection->handle($editor, $section);
        }
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    private function create(User $editor, CmsPage $page, array $definition): void
    {
        $section = $this->createSection->handle($editor, $page, new NewCmsSection(
            ScalarValue::string($definition['key'] ?? null),
            CmsSectionType::from(ScalarValue::string($definition['type'] ?? null)),
            CmsContentDefinition::isEnabled($definition),
            new CmsContentChanges(CmsContentDefinition::attributesOf($definition, ['background']), null),
        ));

        foreach (CmsContentDefinition::itemsOf($definition) as $item) {
            $this->createItem->handle($editor, $section, new NewCmsItem(
                CmsContentDefinition::isEnabled($item),
                new CmsContentChanges(CmsContentDefinition::attributesOf($item), null),
            ));
        }
    }
}

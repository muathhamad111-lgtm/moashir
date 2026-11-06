<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Cms\PublishCmsPage;
use App\Enums\Cms\CmsPageKey;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Cms\SiteSettings;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class DemoPagesInstaller
{
    private const array CONTENT_FIELDS = ['title', 'subtitle', 'description', 'cta_label', 'cta_url', 'icon', 'meta'];

    public function __construct(private PublishCmsPage $publish) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function install(User $editor, array $content): int
    {
        $pages = is_array($content['pages'] ?? null) ? $content['pages'] : [];
        $published = 0;

        foreach ($pages as $key => $definition) {
            $page = in_array((string) $key, CmsPageKey::values(), true) ? CmsPage::query()->where('key', (string) $key)->first() : null;

            if ($page instanceof CmsPage && is_array($definition) && ! $page->sections()->exists()) {
                $this->fillPage($page, $definition, $editor);
                $this->publish->handle($editor, $page);
                $published++;
            }
        }

        $this->fillSiteSettings($content, $editor);

        return $published;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function replace(User $editor, array $content, CmsPageKey $key): bool
    {
        $pages = is_array($content['pages'] ?? null) ? $content['pages'] : [];
        $definition = $pages[$key->value] ?? null;
        $page = CmsPage::query()->where('key', $key)->first();

        if (! $page instanceof CmsPage || ! is_array($definition)) {
            return false;
        }

        CmsSectionItem::query()->whereIn('cms_section_id', $page->sections()->select('id'))->delete();
        $page->sections()->delete();
        $this->fillPage($page, $definition, $editor);
        $this->publish->handle($editor, $page);

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    private function fillPage(CmsPage $page, array $definition, User $editor): void
    {
        $page->fill(['title' => $definition['title'] ?? null, 'seo' => $definition['seo'] ?? []]);
        $page->forceFill(['content_updated_at' => Date::now(), 'has_draft_changes' => true, 'updated_by_user_id' => $editor->id])->save();

        foreach (DemoContentSource::listOf($definition, 'sections') as $position => $sectionData) {
            $section = new CmsSection;
            $section->fill([...$this->contentOf($sectionData), 'key' => $sectionData['key'] ?? 'section-'.$position, 'type' => $sectionData['type'] ?? 'rich_text', 'background' => $sectionData['background'] ?? null]);
            $section->forceFill(['cms_page_id' => $page->id, 'sort_order' => $position + 1, 'updated_by_user_id' => $editor->id])->save();

            foreach (DemoContentSource::listOf($sectionData, 'items') as $itemPosition => $itemData) {
                $item = new CmsSectionItem;
                $item->fill($this->contentOf($itemData));
                $item->forceFill(['cms_section_id' => $section->id, 'sort_order' => $itemPosition + 1, 'updated_by_user_id' => $editor->id])->save();
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private function contentOf(array $data): array
    {
        $fields = [];

        foreach (self::CONTENT_FIELDS as $field) {
            $fields[$field] = $data[$field] ?? null;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function fillSiteSettings(array $content, User $editor): void
    {
        $settings = SiteSettings::current();
        $values = is_array($content['site_settings'] ?? null) ? $content['site_settings'] : [];

        if ($settings->contact !== null || $values === []) {
            return;
        }

        $settings->fill([
            'social' => $values['social'] ?? [],
            'contact' => $values['contact'] ?? null,
            'footer_note' => $values['footer_note'] ?? null,
            'quick_links' => $values['quick_links'] ?? [],
        ]);
        $settings->forceFill(['updated_by_user_id' => $editor->id])->save();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Support\Files\PublicAssetLink;
use App\Support\ValueObjects\LocalizedContent;

final class CmsSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(CmsPage $page): array
    {
        $sections = $page->sections()
            ->where('is_enabled', true)
            ->with(['image', 'items' => static fn ($items) => $items->where('is_enabled', true)->with('image')])
            ->get();

        return [
            'key' => $page->key->value,
            'title' => $this->text($page->title),
            'seo' => $this->seo($page->seo),
            'sections' => $sections->map(fn (CmsSection $section): array => $this->section($section))->values()->all(),
            'updated_at' => $page->content_updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function section(CmsSection $section): array
    {
        return [
            'id' => $section->id,
            'key' => $section->key,
            'type' => $section->type->value,
            ...$this->content($section),
            'background' => $section->background,
            'items' => $section->items->map(fn (CmsSectionItem $item): array => ['id' => $item->id, ...$this->content($item)])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function content(CmsSection|CmsSectionItem $content): array
    {
        return [
            'title' => $this->text($content->title),
            'subtitle' => $this->text($content->subtitle),
            'description' => $this->text($content->description),
            'cta_label' => $this->text($content->cta_label),
            'cta_url' => $content->cta_url,
            'icon' => $content->icon,
            'image' => PublicAssetLink::of($content->image),
            'meta' => $content->meta === [] ? null : $content->meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array{title: array{ar: string, en: string}|null, description: array{ar: string, en: string}|null}
     */
    private function seo(array $seo): array
    {
        return [
            'title' => $this->text(is_array($seo['title'] ?? null) ? LocalizedContent::tryFromArray($seo['title']) : null),
            'description' => $this->text(is_array($seo['description'] ?? null) ? LocalizedContent::tryFromArray($seo['description']) : null),
        ];
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private function text(?LocalizedContent $text): ?array
    {
        return $text?->withFallback();
    }
}

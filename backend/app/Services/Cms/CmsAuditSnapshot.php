<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Support\ValueObjects\LocalizedContent;

final class CmsAuditSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function of(CmsSection|CmsSectionItem $content): array
    {
        $snapshot = [
            'enabled' => $content->is_enabled,
            'order' => $content->sort_order,
            'title' => $this->text($content->title),
            'subtitle' => $this->text($content->subtitle),
            'description' => $this->text($content->description),
            'cta_label' => $this->text($content->cta_label),
            'cta_url' => $content->cta_url,
            'icon' => $content->icon,
            'image_file_id' => $content->image_file_id,
            'meta' => $content->meta,
        ];

        if ($content instanceof CmsSection) {
            return ['key' => $content->key, 'type' => $content->type->value, 'background' => $content->background, ...$snapshot];
        }

        return ['section_id' => $content->cms_section_id, ...$snapshot];
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private function text(?LocalizedContent $text): ?array
    {
        return $text?->toArray();
    }
}

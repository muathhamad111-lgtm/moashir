<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\Cms\CmsSectionType;
use App\Models\Cms\CmsPage;
use App\Support\RichText\RichTextParser;

final readonly class PublishedPagePayload
{
    public function __construct(private RichTextParser $parser) {}

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<string, mixed>
     */
    public function of(CmsPage $page, array $content): array
    {
        $publishedAt = $page->published_at?->toIso8601String();
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $payload = [];

        foreach ($content as $field => $value) {
            $payload[(string) $field] = $value;
        }

        return [
            ...$payload,
            'sections' => array_map($this->withBlocks(...), array_values($sections)),
            'published_at' => $publishedAt,
            'updated_at' => is_string($content['updated_at'] ?? null) ? $content['updated_at'] : $publishedAt,
        ];
    }

    private function withBlocks(mixed $section): mixed
    {
        if (! is_array($section) || ($section['type'] ?? null) !== CmsSectionType::RichText->value) {
            return $section;
        }

        $description = $section['description'] ?? null;
        $isLocalized = is_array($description) && is_string($description['ar'] ?? null) && is_string($description['en'] ?? null);

        return [...$section, 'blocks' => $isLocalized ? $this->parser->localized(['ar' => $description['ar'], 'en' => $description['en']]) : null];
    }
}

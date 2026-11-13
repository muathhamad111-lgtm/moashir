<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string DROPPED = 'demo';

    private const string REPLACEMENT = 'live';

    private const array CONTENT_TABLES = ['cms_sections', 'cms_section_items'];

    public function up(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            DB::table($table)
                ->where('meta->availability', self::DROPPED)
                ->update(['meta->availability' => self::REPLACEMENT]);
        }

        foreach (DB::table('cms_pages')->whereNotNull('published_content')->pluck('published_content', 'id') as $pageId => $published) {
            $this->replaceInPublishedPage((int) $pageId, $published);
        }
    }

    public function down(): void {}

    private function replaceInPublishedPage(int $pageId, mixed $published): void
    {
        $content = json_decode(is_string($published) ? $published : '', true);

        if (! is_array($content) || ! is_array($content['sections'] ?? null)) {
            return;
        }

        $sections = array_map($this->replaceInSection(...), $content['sections']);

        if ($sections === $content['sections']) {
            return;
        }

        DB::table('cms_pages')->where('id', $pageId)->update([
            'published_content' => json_encode([...$content, 'sections' => $sections], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private function replaceInSection(mixed $section): mixed
    {
        if (! is_array($section)) {
            return $section;
        }

        $section = $this->replaceInContent($section);

        if (is_array($section['items'] ?? null)) {
            $section['items'] = array_map(fn (mixed $item): mixed => is_array($item) ? $this->replaceInContent($item) : $item, $section['items']);
        }

        return $section;
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    private function replaceInContent(array $content): array
    {
        if (is_array($content['meta'] ?? null) && ($content['meta']['availability'] ?? null) === self::DROPPED) {
            $content['meta']['availability'] = self::REPLACEMENT;
        }

        return $content;
    }
};

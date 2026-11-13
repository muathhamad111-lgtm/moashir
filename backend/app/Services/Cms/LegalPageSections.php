<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Data\Cms\LegalPageSection;
use App\Support\ValueObjects\LocalizedContent;
use App\Support\ValueObjects\LocalizedText;
use RuntimeException;

final class LegalPageSections
{
    private const string SECTION_HEADING = '/^## +(.+?) *$/u';

    private const string INTRO_KEY = 'intro';

    /**
     * @return list<LegalPageSection>
     */
    public function of(LocalizedText $body): array
    {
        $arabic = $this->split($body->ar);
        $english = $this->split($body->en);

        if (count($arabic) !== count($english)) {
            throw new RuntimeException('The Arabic and English texts must have the same "## " sections.');
        }

        $sections = [];
        $numbered = 0;

        foreach ($arabic as $index => $chunk) {
            $key = $chunk['title'] === null ? self::INTRO_KEY : 'section-'.++$numbered;
            $sections[] = new LegalPageSection(
                $key,
                LocalizedContent::tryFromArray(['ar' => $chunk['title'] ?? '', 'en' => $english[$index]['title'] ?? '']),
                LocalizedContent::tryFromArray(['ar' => $chunk['body'], 'en' => $english[$index]['body']]),
            );
        }

        return $sections;
    }

    /**
     * @return list<array{title: string|null, body: string}>
     */
    private function split(string $text): array
    {
        $chunks = [];
        $title = null;
        $lines = [];

        foreach (explode("\n", $text) as $line) {
            if (preg_match(self::SECTION_HEADING, $line, $heading) === 1) {
                $chunks[] = ['title' => $title, 'body' => trim(implode("\n", $lines))];
                $title = trim($heading[1]);
                $lines = [];

                continue;
            }

            $lines[] = $line;
        }

        $chunks[] = ['title' => $title, 'body' => trim(implode("\n", $lines))];

        return array_values(array_filter($chunks, static fn (array $chunk): bool => $chunk['title'] !== null || $chunk['body'] !== ''));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Data\Cms\LegalDocumentText;
use App\Enums\Cms\LegalDocument;
use App\Rules\FreeOfHtmlMarkup;
use App\Support\RichText\TitledText;
use App\Support\ValueObjects\LocalizedText;
use RuntimeException;

final class LegalDocumentSource
{
    public const string SEED_VERSION = '2026-11-draft';

    private const string DIRECTORY = 'data/legal';

    public function read(LegalDocument $document): LegalDocumentText
    {
        [$arabicTitle, $arabicBody] = $this->titledText($document, 'ar');
        [$englishTitle, $englishBody] = $this->titledText($document, 'en');

        return new LegalDocumentText(new LocalizedText($arabicTitle, $englishTitle), new LocalizedText($arabicBody, $englishBody));
    }

    /**
     * @return array{string, string}
     */
    private function titledText(LegalDocument $document, string $locale): array
    {
        $file = sprintf('%s.%s.md', $document->value, $locale);
        $path = database_path(self::DIRECTORY.'/'.$file);
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        if (! mb_check_encoding($contents, 'UTF-8') || FreeOfHtmlMarkup::contains($contents)) {
            throw new RuntimeException(sprintf('The legal draft %s is missing, is not UTF-8 or contains HTML markup.', $file));
        }

        $text = TitledText::fromMarkdown($contents);

        if ($text->title === null || $text->body === '') {
            throw new RuntimeException(sprintf('The legal draft %s needs a leading "# " title and a text.', $file));
        }

        return [$text->title, $text->body];
    }
}

<?php

declare(strict_types=1);

namespace App\Support\RichText;

final class RichTextParser
{
    public function parse(string $text): RichTextDocument
    {
        $builder = new RichTextDocumentBuilder;

        foreach (explode("\n", RichTextSanitizer::clean($text)) as $line) {
            $builder->add($line);
        }

        return $builder->build();
    }

    /**
     * @param  array{ar: string, en: string}  $text
     * @return array{ar: list<array<string, mixed>>, en: list<array<string, mixed>>}
     */
    public function localized(array $text): array
    {
        return ['ar' => $this->parse($text['ar'])->toArray(), 'en' => $this->parse($text['en'])->toArray()];
    }
}

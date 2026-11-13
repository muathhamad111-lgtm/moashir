<?php

declare(strict_types=1);

namespace App\Support\RichText;

final readonly class TitledText
{
    private const string LEADING_TITLE = '/\A# +(.+?) *(?:\n|\z)/u';

    public function __construct(public ?string $title, public string $body) {}

    public static function fromMarkdown(string $text): self
    {
        $clean = RichTextSanitizer::clean($text);

        if (preg_match(self::LEADING_TITLE, $clean, $match) !== 1) {
            return new self(null, $clean);
        }

        return new self(trim($match[1]), trim(substr($clean, strlen($match[0]))));
    }
}

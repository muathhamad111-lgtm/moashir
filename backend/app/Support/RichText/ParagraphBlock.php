<?php

declare(strict_types=1);

namespace App\Support\RichText;

use App\Enums\Cms\RichTextBlockType;

final readonly class ParagraphBlock implements RichTextBlock
{
    public function __construct(public string $text) {}

    /**
     * @return array{type: string, text: string}
     */
    public function toArray(): array
    {
        return ['type' => RichTextBlockType::Paragraph->value, 'text' => $this->text];
    }
}

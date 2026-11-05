<?php

declare(strict_types=1);

namespace App\Support\RichText;

use App\Enums\Cms\RichTextBlockType;

final readonly class HeadingBlock implements RichTextBlock
{
    public function __construct(public int $level, public string $text) {}

    /**
     * @return array{type: string, level: int, text: string}
     */
    public function toArray(): array
    {
        return ['type' => RichTextBlockType::Heading->value, 'level' => $this->level, 'text' => $this->text];
    }
}

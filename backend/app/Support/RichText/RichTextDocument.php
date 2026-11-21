<?php

declare(strict_types=1);

namespace App\Support\RichText;

final readonly class RichTextDocument
{
    /**
     * @param  list<RichTextBlock>  $blocks
     */
    public function __construct(public array $blocks) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (RichTextBlock $block): array => $block->toArray(), $this->blocks);
    }
}

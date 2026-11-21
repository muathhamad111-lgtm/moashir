<?php

declare(strict_types=1);

namespace App\Support\RichText;

use App\Enums\Cms\RichTextBlockType;

final readonly class ListBlock implements RichTextBlock
{
    /**
     * @param  list<string>  $items
     */
    public function __construct(public bool $ordered, public ?int $start, public array $items) {}

    /**
     * @return array{type: string, ordered: bool, start: int|null, items: list<string>}
     */
    public function toArray(): array
    {
        return ['type' => RichTextBlockType::ItemList->value, 'ordered' => $this->ordered, 'start' => $this->start, 'items' => $this->items];
    }
}

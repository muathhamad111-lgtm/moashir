<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum RichTextBlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case ItemList = 'list';
    case Table = 'table';
}

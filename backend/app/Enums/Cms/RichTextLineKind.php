<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum RichTextLineKind
{
    case Text;
    case BulletItem;
    case NumberedItem;
    case TableRow;
}

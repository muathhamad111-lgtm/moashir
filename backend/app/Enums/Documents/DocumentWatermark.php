<?php

declare(strict_types=1);

namespace App\Enums\Documents;

enum DocumentWatermark: string
{
    case Test = 'TEST';
    case Void = 'VOID';
}

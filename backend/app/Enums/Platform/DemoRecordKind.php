<?php

declare(strict_types=1);

namespace App\Enums\Platform;

enum DemoRecordKind: string
{
    case Entity = 'entity';
    case User = 'user';
}

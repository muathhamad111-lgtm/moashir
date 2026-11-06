<?php

declare(strict_types=1);

namespace App\Enums\Platform;

enum ReadinessCheckStatus: string
{
    case Ok = 'ok';
    case Failing = 'failing';
    case Skipped = 'skipped';
}

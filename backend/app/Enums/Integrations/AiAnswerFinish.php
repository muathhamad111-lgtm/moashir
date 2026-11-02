<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum AiAnswerFinish: string
{
    case Complete = 'complete';
    case Truncated = 'truncated';
    case Refused = 'refused';
    case Blocked = 'blocked';
}

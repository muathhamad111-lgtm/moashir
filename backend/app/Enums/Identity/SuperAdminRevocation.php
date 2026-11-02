<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum SuperAdminRevocation
{
    case KeepAtLeastOne;
    case AllowLast;
}

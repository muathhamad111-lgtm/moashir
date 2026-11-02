<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum EmailChangePolicy: string
{
    case RequiresApproval = 'requires_approval';
    case ImmediateNotify = 'immediate_notify';
}

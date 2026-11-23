<?php

declare(strict_types=1);

namespace App\Enums\Nda;

enum NdaReminderSource: string
{
    case Auto = 'auto';
    case Manual = 'manual';
}

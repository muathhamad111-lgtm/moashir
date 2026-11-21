<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum AccessIntent: string
{
    case View = 'view';
    case Download = 'download';
}

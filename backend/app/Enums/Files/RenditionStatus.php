<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum RenditionStatus: string
{
    case Ready = 'ready';
    case Failed = 'failed';
}

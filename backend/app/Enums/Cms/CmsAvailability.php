<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum CmsAvailability: string
{
    case Live = 'live';
    case ComingSoon = 'coming_soon';
}

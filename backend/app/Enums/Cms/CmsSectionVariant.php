<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum CmsSectionVariant: string
{
    case Journey = 'journey';
    case Cta = 'cta';
    case Band = 'band';
}

<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum InsightsCallToAction: string
{
    case StartAccredited = 'start_accredited';
    case ViewRequest = 'view_request';
}

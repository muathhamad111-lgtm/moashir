<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum ComingSoonReport: string
{
    case Periodic = 'periodic';
    case Annual = 'annual';
    case Benchmark = 'benchmark';
}

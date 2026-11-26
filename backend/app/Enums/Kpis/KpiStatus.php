<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

enum KpiStatus: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case NeedsImprovement = 'needs_improvement';
    case BehindTarget = 'behind_target';
}

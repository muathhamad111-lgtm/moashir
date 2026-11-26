<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Enums\Identity\Ability;

enum OverviewBreakdownKey: string
{
    case RequestsByStatus = 'requests_by_status';
    case PaymentsByStatus = 'payments_by_status';
    case EntitiesByType = 'entities_by_type';
    case EntitiesBySize = 'entities_by_size';
    case EntitiesByRegion = 'entities_by_region';
    case AssessorWorkload = 'assessor_workload';
    case KpiTargets = 'kpi_targets';
    case Finance = 'finance';
    case Award = 'award';
    case Inbox = 'inbox';
    case Users = 'users';
    case Storage = 'storage';

    public function requiredAbility(): ?Ability
    {
        return $this === self::Storage ? Ability::ViewStorageUsage : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final class CriteriaActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::CriteriaManage->value);
    }

    public function itemsFor(User $user): array
    {
        $draft = CriteriaVersion::query()->draft()->first(['id', 'validation_errors']);
        $problems = count($draft->validation_errors ?? []);

        return $problems > 0 ? [new ActionItem('fix_criteria_draft', $problems, ActionSeverity::Warning, Links::CRITERIA)] : [];
    }
}

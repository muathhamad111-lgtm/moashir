<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final class ContentActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::ContentHomeManage->value);
    }

    public function itemsFor(User $user): array
    {
        $pages = CmsPage::query()->where('has_draft_changes', true)->count();

        return $pages > 0 ? [new ActionItem('publish_content_changes', $pages, ActionSeverity::Info, Links::HOMEPAGE)] : [];
    }
}

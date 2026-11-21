<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final class EntityAdministrationActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::EntitiesEmailChangeApprove->value);
    }

    public function itemsFor(User $user): array
    {
        $emailChanges = EmailChangeRequest::query()->whereNotNull('entity_id')->where('status', EmailChangeStatus::AwaitingApproval)->count();

        return $emailChanges > 0
            ? [new ActionItem('review_entity_email_changes', $emailChanges, ActionSeverity::Warning, Links::ENTITY_EMAIL_CHANGES)]
            : [];
    }
}

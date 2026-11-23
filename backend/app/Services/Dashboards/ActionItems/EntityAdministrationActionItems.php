<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Entities\ProfileChangeStatus;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final class EntityAdministrationActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::EntitiesEmailChangeApprove->value) || $user->can(PermissionKey::EntitiesProfileChangeApprove->value);
    }

    public function itemsFor(User $user): array
    {
        return array_values(array_filter([
            $user->can(PermissionKey::EntitiesEmailChangeApprove->value) ? $this->emailChanges() : null,
            $user->can(PermissionKey::EntitiesProfileChangeApprove->value) ? $this->profileChanges() : null,
        ]));
    }

    private function emailChanges(): ?ActionItem
    {
        $count = EmailChangeRequest::query()->whereNotNull('entity_id')->where('status', EmailChangeStatus::AwaitingApproval)->count();

        return $count > 0 ? new ActionItem('review_entity_email_changes', $count, ActionSeverity::Warning, Links::ENTITY_EMAIL_CHANGES) : null;
    }

    private function profileChanges(): ?ActionItem
    {
        $count = EntityProfileChangeRequest::query()->where('status', ProfileChangeStatus::Pending)->count();

        return $count > 0 ? new ActionItem('review_entity_profile_changes', $count, ActionSeverity::Warning, Links::ENTITY_PROFILE_CHANGES) : null;
    }
}

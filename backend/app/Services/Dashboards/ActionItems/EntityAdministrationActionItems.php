<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Entities\PrivacyRequestStatus;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final class EntityAdministrationActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return $this->approvesEmailChanges($user) || $this->handlesPrivacyRequests($user);
    }

    public function itemsFor(User $user): array
    {
        $emailChanges = $this->approvesEmailChanges($user)
            ? EmailChangeRequest::query()->whereNotNull('entity_id')->where('status', EmailChangeStatus::AwaitingApproval)->count()
            : 0;
        $privacyRequests = $this->handlesPrivacyRequests($user)
            ? PrivacyRequest::query()->where('status', PrivacyRequestStatus::Open)->count()
            : 0;

        return array_values(array_filter([
            $emailChanges > 0 ? new ActionItem('review_entity_email_changes', $emailChanges, ActionSeverity::Warning, Links::ENTITY_EMAIL_CHANGES) : null,
            $privacyRequests > 0 ? new ActionItem('handle_privacy_requests', $privacyRequests, ActionSeverity::Warning, Links::PRIVACY_REQUESTS) : null,
        ]));
    }

    private function approvesEmailChanges(User $user): bool
    {
        return $user->can(PermissionKey::EntitiesEmailChangeApprove->value);
    }

    private function handlesPrivacyRequests(User $user): bool
    {
        return $user->can(PermissionKey::PrivacyRequestsManage->value);
    }
}

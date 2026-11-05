<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final class ContactActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::ContactMessagesManage->value);
    }

    public function itemsFor(User $user): array
    {
        $messages = ContactMessage::query()->where('status', ContactMessageStatus::New)->count();

        return $messages > 0 ? [new ActionItem('answer_contact_messages', $messages, ActionSeverity::Warning, Links::newContactMessages())] : [];
    }
}

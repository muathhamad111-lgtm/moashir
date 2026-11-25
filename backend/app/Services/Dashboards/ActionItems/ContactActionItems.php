<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Contact\ContactInboxStatus;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Services\Contact\ContactInboxStatusQuery;
use App\Support\Dashboards\DashboardLinks as Links;

final readonly class ContactActionItems implements ActionItemSource
{
    public function __construct(private ContactInboxStatusQuery $statuses) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::ContactMessagesManage->value);
    }

    /**
     * The open messages nobody has taken or answered yet: the derived "new" inbox status.
     */
    public function itemsFor(User $user): array
    {
        $query = ContactMessage::query()->where('status', '!=', ContactMessageStatus::Closed);
        $this->statuses->constrain($query, ContactInboxStatus::New);
        $messages = $query->count();

        return $messages > 0 ? [new ActionItem('answer_contact_messages', $messages, ActionSeverity::Warning, Links::newContactMessages())] : [];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Data\Dashboards\ActionItemContext;
use App\Enums\Dashboards\ActionSeverity;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Support\Contact\ContactLinks;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;

final class SenderContactActionItems implements ActionItemSource
{
    public function isAvailableTo(User $user): bool
    {
        return true;
    }

    public function itemsFor(User $user): array
    {
        $latest = ContactMessage::query()
            ->sentBy($user->id)
            ->withUnreadReply()
            ->select(['public_id', 'reference', 'user_id'])
            ->selectRaw('count(*) over () as unread_messages')
            ->orderByDesc('replied_at')
            ->orderByDesc('id')
            ->first();
        $count = ScalarValue::int($latest?->getAttribute('unread_messages'));

        if (! $latest instanceof ContactMessage || $count === 0) {
            return [];
        }

        return [$count === 1
            ? new ActionItem('read_contact_replies', 1, ActionSeverity::Info, ContactLinks::sender($latest) ?? Links::MY_MESSAGES, null, ActionItemContext::forMessage($latest->public_id, $latest->reference))
            : new ActionItem('read_contact_replies', $count, ActionSeverity::Info, Links::MY_MESSAGES, null, ActionItemContext::none())];
    }
}

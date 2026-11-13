<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Contact\OwnContactMessagesSummary;
use App\Enums\Contact\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Support\StatusCounts;

final class SummarizeOwnContactMessages
{
    public function handle(User $sender): OwnContactMessagesSummary
    {
        $own = ContactMessage::query()->sentBy($sender->id);

        return new OwnContactMessagesSummary(
            StatusCounts::of($own, ContactMessageStatus::cases()),
            (clone $own)->withUnreadReply()->count(),
        );
    }
}

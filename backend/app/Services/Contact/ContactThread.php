<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\Contact\ContactEntryKind;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;

final class ContactThread
{
    public function lock(ContactMessage $message): ContactMessage
    {
        return ContactMessage::query()->whereKey($message->id)->lockForUpdate()->firstOrFail();
    }

    public function append(ContactMessage $message, ContactEntryKind $kind, string $body, ?User $author): ContactMessageEntry
    {
        $entry = new ContactMessageEntry(['kind' => $kind, 'body' => $body, 'author_user_id' => $author?->id]);
        $entry->forceFill(['contact_message_id' => $message->id])->save();

        return $entry;
    }
}

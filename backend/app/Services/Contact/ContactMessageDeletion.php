<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Support\ScalarValue;

final class ContactMessageDeletion
{
    /**
     * @param  list<int>  $messageIds
     * @return array{messages: int, entries: int}
     */
    public function delete(array $messageIds): array
    {
        if ($messageIds === []) {
            return ['messages' => 0, 'entries' => 0];
        }

        $entries = ScalarValue::int(ContactMessageEntry::query()->whereIn('contact_message_id', $messageIds)->delete());
        $messages = ScalarValue::int(ContactMessage::query()->whereKey($messageIds)->delete());

        return ['messages' => $messages, 'entries' => $entries];
    }
}

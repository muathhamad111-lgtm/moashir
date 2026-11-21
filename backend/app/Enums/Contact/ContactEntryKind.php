<?php

declare(strict_types=1);

namespace App\Enums\Contact;

enum ContactEntryKind: string
{
    case Message = 'message';
    case Reply = 'reply';
    case Note = 'note';

    public function isVisibleToSender(): bool
    {
        return $this !== self::Note;
    }

    public function isWrittenBySender(): bool
    {
        return $this === self::Message;
    }

    /**
     * @return list<self>
     */
    public static function senderVisible(): array
    {
        return [self::Message, self::Reply];
    }
}

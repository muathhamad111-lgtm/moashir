<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum CommitteeDiscussionMode: string
{
    case Session = 'session';
    case AgreedOutcome = 'agreed_outcome';

    /**
     * @return list<DiscussionEntryKind>
     */
    public function entryKinds(): array
    {
        return match ($this) {
            self::Session => [DiscussionEntryKind::Opinion],
            self::AgreedOutcome => [DiscussionEntryKind::Approve, DiscussionEntryKind::RequestEdit],
        };
    }
}

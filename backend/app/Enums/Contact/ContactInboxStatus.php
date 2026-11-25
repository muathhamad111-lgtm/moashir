<?php

declare(strict_types=1);

namespace App\Enums\Contact;

use Carbon\CarbonInterface;

/**
 * The staff inbox status, derived from the data and never stored: before the team's first reply the
 * assignee decides it (new / assigned); after it, whether the sender wrote again since the team's
 * latest reply (awaiting reply / replied). Closing a message does not change it.
 */
enum ContactInboxStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Replied = 'replied';
    case AwaitingReply = 'awaiting_reply';

    public static function derive(bool $isAssigned, ?CarbonInterface $repliedAt, ?CarbonInterface $senderLastMessageAt): self
    {
        if (! $repliedAt instanceof CarbonInterface) {
            return $isAssigned ? self::Assigned : self::New;
        }

        return $senderLastMessageAt instanceof CarbonInterface && $senderLastMessageAt->greaterThan($repliedAt) ? self::AwaitingReply : self::Replied;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

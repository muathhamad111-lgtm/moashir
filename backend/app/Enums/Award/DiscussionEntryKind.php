<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum DiscussionEntryKind: string
{
    case Opinion = 'opinion';
    case Approve = 'approve';
    case RequestEdit = 'request_edit';

    public function requiresBody(): bool
    {
        return $this !== self::Approve;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums\Platform;

enum HousekeepingTask: string
{
    case OrphanFilePurge = 'orphan_file_purge';
    case ContactMessagePurge = 'contact_message_purge';
    case ContactMessageErasure = 'contact_message_erasure';
    case DemoPurge = 'demo_purge';
}

<?php

declare(strict_types=1);

namespace App\Enums\Entities;

/**
 * How much of an entity's account timeline a viewer may read: the full record scope adds the profile and e-mail
 * changes (with their values) to the account's own lifecycle events.
 */
enum EntityTimelineScope
{
    case Full;
    case Lifecycle;
}

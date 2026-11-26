<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

/**
 * The stages of an accredited assessment request as the entity's e-mails show them, in order.
 */
enum AccreditedJourneyStage: string
{
    case Submitted = 'submitted';
    case Assignment = 'assignment';
    case Review = 'review';
    case Result = 'result';

    public function position(): int
    {
        return match ($this) {
            self::Submitted => 0,
            self::Assignment => 1,
            self::Review => 2,
            self::Result => 3,
        };
    }
}

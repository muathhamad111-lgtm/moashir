<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

enum JourneyStepState: string
{
    case Done = 'done';
    case Current = 'current';
    case Next = 'next';

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /**
     * Done and current steps carry a short state label next to their name; the next ones do not.
     */
    public function isLabelled(): bool
    {
        return $this !== self::Next;
    }
}

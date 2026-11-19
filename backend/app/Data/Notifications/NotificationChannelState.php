<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationChannel;

final readonly class NotificationChannelState
{
    public function __construct(
        public NotificationChannel $channel,
        public bool $isEnabled,
        public bool $isShipped,
        public bool $isSwitchable,
    ) {}
}

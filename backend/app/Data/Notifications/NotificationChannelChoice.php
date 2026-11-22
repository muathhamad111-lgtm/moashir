<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;

/**
 * One channel choice: send (or stop) one type by one channel to one audience.
 */
final readonly class NotificationChannelChoice
{
    public function __construct(
        public NotificationType $type,
        public NotificationChannel $channel,
        public NotificationAudienceKind $audience,
        public bool $enabled,
    ) {}
}

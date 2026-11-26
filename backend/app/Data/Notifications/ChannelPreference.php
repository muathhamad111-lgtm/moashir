<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationChannel;

final readonly class ChannelPreference
{
    public function __construct(public NotificationChannel $channel, public bool $enabled, public bool $configurable) {}
}

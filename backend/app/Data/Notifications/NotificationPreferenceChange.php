<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;

final readonly class NotificationPreferenceChange
{
    public function __construct(public NotificationCode $code, public NotificationChannel $channel, public bool $enabled) {}
}

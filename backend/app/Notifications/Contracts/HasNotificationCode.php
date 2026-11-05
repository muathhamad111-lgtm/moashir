<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use App\Enums\Notifications\NotificationCode;

interface HasNotificationCode
{
    public function notificationCode(): NotificationCode;
}

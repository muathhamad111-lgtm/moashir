<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

enum NotificationCategory: string
{
    case ActionRequired = 'action_required';
    case Informational = 'informational';
    case Alert = 'alert';
    case Error = 'error';
}

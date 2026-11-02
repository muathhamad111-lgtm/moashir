<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Notification;

final readonly class SecurityAlerter
{
    public function __construct(private Repository $config) {}

    public function raise(NotificationCode $code): void
    {
        Notification::send(User::query()->superAdmins()->get(), new SecurityAlertNotification($code));

        $mailbox = $this->config->get('moashir.security.alert_mail');

        if (is_string($mailbox) && $mailbox !== '') {
            Notification::route('mail', $mailbox)->notify((new SecurityAlertNotification($code))->locale($this->config->string('app.fallback_locale')));
        }
    }
}

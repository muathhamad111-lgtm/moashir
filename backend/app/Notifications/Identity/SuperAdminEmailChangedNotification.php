<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Notifications\QueuedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;

final class SuperAdminEmailChangedNotification extends QueuedNotification
{
    public function __construct(private readonly string $maskedNewEmail)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.super_admin_email_changed.subject'))
            ->line(__('notifications.super_admin_email_changed.line', ['email' => $this->maskedNewEmail]))
            ->line(__('notifications.super_admin_email_changed.advice'));
    }
}

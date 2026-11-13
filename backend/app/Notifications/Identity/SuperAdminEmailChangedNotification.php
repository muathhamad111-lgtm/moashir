<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Enums\Notifications\MailReason;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use Illuminate\Notifications\AnonymousNotifiable;

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

    public function toMail(AnonymousNotifiable $notifiable): BrandedMailMessage
    {
        return BrandedMailMessage::make($notifiable, MailReason::LinkedAddress)
            ->subject(__('notifications.super_admin_email_changed.subject'))
            ->line(__('notifications.super_admin_email_changed.line', ['email' => $this->maskedNewEmail]))
            ->line(__('notifications.super_admin_email_changed.advice'));
    }
}

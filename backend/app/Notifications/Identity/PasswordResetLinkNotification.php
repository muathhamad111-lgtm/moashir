<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Models\Identity\User;
use App\Notifications\QueuedNotification;
use App\Support\FrontendUrl;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordResetLinkNotification extends QueuedNotification
{
    public function __construct(private readonly string $token, private readonly int $validMinutes)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.password_reset.subject'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.password_reset.line'))
            ->action(__('notifications.password_reset.action'), FrontendUrl::to('auth/reset-password/'.$this->token))
            ->line(__('notifications.password_reset.expiry', ['minutes' => $this->validMinutes]))
            ->line(__('notifications.password_reset.ignore'));
    }
}

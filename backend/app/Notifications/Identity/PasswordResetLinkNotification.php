<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use App\Support\FrontendUrl;

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

    public function toMail(User $notifiable): BrandedMailMessage
    {
        return BrandedMailMessage::make($notifiable, MailReason::Account)
            ->subject(__('notifications.password_reset.subject'))
            ->line(__('notifications.password_reset.line'))
            ->action(__('notifications.password_reset.action'), FrontendUrl::to('auth/reset-password/'.$this->token))
            ->line(__('notifications.password_reset.expiry', ['duration' => trans_choice('quantities.minutes', $this->validMinutes)]))
            ->line(__('notifications.password_reset.ignore'));
    }
}

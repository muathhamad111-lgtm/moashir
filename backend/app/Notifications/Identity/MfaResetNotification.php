<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Data\Notifications\InAppMessage;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Contracts\DeliversInApp;
use App\Notifications\QueuedNotification;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Notifications\Messages\MailMessage;

final class MfaResetNotification extends QueuedNotification implements DeliversInApp
{
    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', InAppChannel::class];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.mfa_reset.subject'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.mfa_reset.line'));
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage(
            NotificationCode::AccountStatusChanged,
            LocalizedText::fromTranslation('notifications.mfa_reset.subject'),
            LocalizedText::fromTranslation('notifications.mfa_reset.line'),
        );
    }
}

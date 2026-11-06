<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Data\Notifications\InAppMessage;
use App\Enums\Notifications\MailReason;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Contracts\DeliversInApp;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use App\Support\ValueObjects\LocalizedText;

final class MfaResetNotification extends QueuedNotification implements DeliversInApp
{
    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', InAppChannel::class];
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        return BrandedMailMessage::make($notifiable, MailReason::Account)
            ->subject(__('notifications.mfa_reset.subject'))
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

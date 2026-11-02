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

final class AccountLockedNotification extends QueuedNotification implements DeliversInApp
{
    public function __construct(private readonly int $lockedUserId, private readonly int $lockedMinutes)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->id === $this->lockedUserId ? ['mail'] : [InAppChannel::class];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.account_locked.subject'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.account_locked.line', ['minutes' => $this->lockedMinutes]))
            ->line(__('notifications.account_locked.advice'));
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage(
            NotificationCode::AccountLocked,
            LocalizedText::fromTranslation('notifications.account_locked.admin_title'),
            LocalizedText::fromTranslation('notifications.account_locked.admin_body'),
            'settings/users/'.$this->lockedUserId,
        );
    }
}

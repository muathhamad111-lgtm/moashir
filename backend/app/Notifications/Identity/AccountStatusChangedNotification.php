<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Data\Notifications\InAppMessage;
use App\Enums\Identity\UserStatus;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Contracts\DeliversInApp;
use App\Notifications\QueuedNotification;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Notifications\Messages\MailMessage;

final class AccountStatusChangedNotification extends QueuedNotification implements DeliversInApp
{
    public function __construct(private readonly UserStatus $newStatus)
    {
        parent::__construct();
    }

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
            ->subject(__($this->translationKey('subject')))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__($this->translationKey('line')));
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage(
            NotificationCode::AccountStatusChanged,
            LocalizedText::fromTranslation($this->translationKey('subject')),
            LocalizedText::fromTranslation($this->translationKey('line')),
        );
    }

    private function translationKey(string $part): string
    {
        return 'notifications.account_status.'.$this->newStatus->value.'.'.$part;
    }
}

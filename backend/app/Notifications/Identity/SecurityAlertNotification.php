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
use Illuminate\Notifications\AnonymousNotifiable;

final class SecurityAlertNotification extends QueuedNotification implements DeliversInApp
{
    public function __construct(private readonly NotificationCode $code)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(User|AnonymousNotifiable $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', InAppChannel::class] : ['mail'];
    }

    public function toMail(User|AnonymousNotifiable $notifiable): BrandedMailMessage
    {
        return BrandedMailMessage::make($notifiable, $notifiable instanceof User ? MailReason::Account : MailReason::SecurityMailbox)
            ->subject(__($this->translationKey('title')))
            ->line(__($this->translationKey('body')))
            ->line(__('notifications.security_alert.footer'));
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage(
            $this->code,
            LocalizedText::fromTranslation($this->translationKey('title')),
            LocalizedText::fromTranslation($this->translationKey('body')),
        );
    }

    public function code(): NotificationCode
    {
        return $this->code;
    }

    private function translationKey(string $part): string
    {
        return 'notifications.security_alert.'.$this->code->value.'.'.$part;
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Entities;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\InAppMessage;
use App\Enums\Notifications\MailReason;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Contracts\DeliversInApp;
use App\Notifications\Contracts\HasNotificationCode;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use App\Support\FrontendUrl;

final class EntityNotification extends QueuedNotification implements DeliversInApp, HasNotificationCode
{
    public function __construct(public readonly EntityNotice $notice)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->notice->viaMail) {
            $channels[] = 'mail';
        }

        if ($this->notice->viaInApp && $notifiable instanceof User) {
            $channels[] = InAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): BrandedMailMessage
    {
        $locale = app()->getLocale();
        $mail = BrandedMailMessage::make($notifiable, $notifiable instanceof User ? MailReason::Account : MailReason::LinkedAddress)
            ->subject($this->notice->title->in($locale))
            ->line($this->notice->body->in($locale));

        if ($this->notice->link !== null) {
            $mail->action(__('notifications.entities.open_platform'), FrontendUrl::to($this->notice->link));
        }

        return $mail;
    }

    public function notificationCode(): NotificationCode
    {
        return $this->notice->code;
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage($this->notice->code, $this->notice->title, $this->notice->body, $this->notice->link, $this->notice->path);
    }
}

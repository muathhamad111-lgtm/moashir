<?php

declare(strict_types=1);

namespace App\Notifications\Entities;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\InAppMessage;
use App\Enums\Integrations\SmsPurpose;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Contracts\DeliversInApp;
use App\Notifications\Contracts\DeliversSms;
use App\Notifications\Contracts\HasNotificationCode;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\Messages\NoticeMail;
use App\Notifications\QueuedNotification;
use App\Services\Notifications\NoticeSms;
use App\Services\Notifications\SystemNotificationChannels;

final class EntityNotification extends QueuedNotification implements DeliversInApp, DeliversSms, HasNotificationCode
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
        $channels = app(SystemNotificationChannels::class);
        $code = $this->notice->code;
        $deliveries = [];

        if ($this->notice->viaMail || $channels->isSwitchedOn($code, NotificationChannel::Email)) {
            $deliveries[] = 'mail';
        }

        if ($notifiable instanceof User && ($this->notice->viaInApp || $channels->isSwitchedOn($code, NotificationChannel::InApp))) {
            $deliveries[] = InAppChannel::class;
        }

        if ($notifiable instanceof User && app(NoticeSms::class)->isSendable($code, $this->notice->text, $notifiable)) {
            $deliveries[] = SmsChannel::class;
        }

        return $deliveries;
    }

    public function toMail(object $notifiable): BrandedMailMessage
    {
        return NoticeMail::of($notifiable, $this->notice->title, $this->notice->body, $this->notice->link);
    }

    public function notificationCode(): NotificationCode
    {
        return $this->notice->code;
    }

    public function toSms(User $notifiable): string
    {
        return $this->notice->text?->sms()?->in(app()->getLocale()) ?? '';
    }

    public function smsPurpose(): SmsPurpose
    {
        return SmsPurpose::Notification;
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage($this->notice->code, $this->notice->title, $this->notice->body, $this->notice->link, $this->notice->path);
    }
}

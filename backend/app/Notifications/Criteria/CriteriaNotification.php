<?php

declare(strict_types=1);

namespace App\Notifications\Criteria;

use App\Data\Criteria\CriteriaNotice;
use App\Data\Mail\MailNotice;
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

final class CriteriaNotification extends QueuedNotification implements DeliversInApp, DeliversSms, HasNotificationCode
{
    public function __construct(public readonly CriteriaNotice $notice)
    {
        parent::__construct();
    }

    /**
     * In-app as shipped; e-mail and SMS too when the System Administrator switched them on for this type.
     *
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $deliveries = [InAppChannel::class];

        if (app(SystemNotificationChannels::class)->isSwitchedOn($this->notice->code, NotificationChannel::Email)) {
            $deliveries[] = 'mail';
        }

        if (app(NoticeSms::class)->isSendable($this->notice->code, $this->notice->text, $notifiable)) {
            $deliveries[] = SmsChannel::class;
        }

        return $deliveries;
    }

    public function notificationCode(): NotificationCode
    {
        return $this->notice->code;
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        return NoticeMail::of($notifiable, new MailNotice($this->notice->code, $this->notice->title, $this->notice->body, $this->notice->link, $this->notice->text));
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
        return new InAppMessage($this->notice->code, $this->notice->title, $this->notice->body, $this->notice->link);
    }
}

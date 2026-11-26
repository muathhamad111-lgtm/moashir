<?php

declare(strict_types=1);

namespace App\Notifications\Contact;

use App\Data\Contact\ContactSenderNotice;
use App\Enums\Notifications\MailReason;
use App\Enums\Notifications\NotificationCode;
use App\Notifications\Contracts\HasNotificationCode;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use App\Support\FrontendUrl;

final class ContactAcknowledgementNotification extends QueuedNotification implements HasNotificationCode
{
    public function __construct(public readonly ContactSenderNotice $notice)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): BrandedMailMessage
    {
        $locale = app()->getLocale();
        $replacements = ['reference' => $this->notice->reference, 'type' => $this->notice->enquiryType->in($locale)];
        $mail = BrandedMailMessage::make($notifiable, MailReason::ContactForm)
            ->subject(__('notifications.contact.acknowledgement.subject', $replacements))
            ->line(__('notifications.contact.acknowledgement.received', $replacements))
            ->line(__('notifications.contact.acknowledgement.next'));

        if ($this->notice->link !== null) {
            $mail->action(__('notifications.contact.open_messages'), FrontendUrl::to($this->notice->link));
        }

        return $mail;
    }

    public function notificationCode(): NotificationCode
    {
        return NotificationCode::ContactMessageAcknowledged;
    }
}

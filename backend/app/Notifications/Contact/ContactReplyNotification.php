<?php

declare(strict_types=1);

namespace App\Notifications\Contact;

use App\Data\Contact\ContactSenderNotice;
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
use App\Support\ValueObjects\LocalizedText;

final class ContactReplyNotification extends QueuedNotification implements DeliversInApp, HasNotificationCode
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
        return $notifiable instanceof User ? ['mail', InAppChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): BrandedMailMessage
    {
        $replacements = ['reference' => $this->notice->reference];
        $mail = BrandedMailMessage::make($notifiable, MailReason::ContactForm)
            ->subject(__('notifications.contact.reply.subject', $replacements))
            ->line(__('notifications.contact.reply.intro', $replacements));

        foreach ($this->paragraphs() as $paragraph) {
            $mail->quote($paragraph);
        }

        return $this->notice->link === null
            ? $mail->line(__('notifications.contact.reply.guest_follow_up', $replacements))
            : $mail->action(__('notifications.contact.open_messages'), FrontendUrl::to($this->notice->link));
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        $replacements = ['reference' => $this->notice->reference];

        return new InAppMessage(
            NotificationCode::ContactReplySent,
            LocalizedText::fromTranslationWith('notifications.contact.CNT-02.title', $replacements),
            LocalizedText::fromTranslationWith('notifications.contact.CNT-02.body', $replacements),
            $this->notice->link,
        );
    }

    public function notificationCode(): NotificationCode
    {
        return NotificationCode::ContactReplySent;
    }

    /**
     * @return list<string>
     */
    private function paragraphs(): array
    {
        $lines = preg_split('/\R{2,}/u', trim((string) $this->notice->replyBody)) ?: [];

        return array_values(array_filter(array_map(trim(...), $lines), static fn (string $line): bool => $line !== ''));
    }
}

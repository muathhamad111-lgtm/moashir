<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use App\Support\FrontendUrl;
use App\Support\ValueObjects\LocalizedText;

/**
 * The e-mail of a notice: its title as the subject, its body as the line and an «open the platform» button
 * when the notice links somewhere.
 */
final class NoticeMail
{
    public static function of(object $notifiable, LocalizedText $title, LocalizedText $body, ?string $link): BrandedMailMessage
    {
        $locale = app()->getLocale();
        $mail = BrandedMailMessage::make($notifiable, $notifiable instanceof User ? MailReason::Account : MailReason::LinkedAddress)
            ->subject($title->in($locale))
            ->line($body->in($locale));

        if ($link !== null) {
            $mail->action(__('notifications.entities.open_platform'), FrontendUrl::to($link));
        }

        return $mail;
    }
}

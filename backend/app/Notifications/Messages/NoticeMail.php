<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Data\Mail\MailNotice;
use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use App\Support\FrontendUrl;
use App\Support\ValueObjects\LocalizedText;

/**
 * The e-mail of a notice: its title as the subject, its category on the band, the template's optional summary
 * line, its body as the line and a button when the notice links somewhere, labelled by the template's own
 * `action` text or the shared «open the platform».
 */
final class NoticeMail
{
    public static function of(object $notifiable, MailNotice $notice): BrandedMailMessage
    {
        $locale = app()->getLocale();
        $mail = BrandedMailMessage::make($notifiable, $notifiable instanceof User ? MailReason::Account : MailReason::LinkedAddress)
            ->subject($notice->title->in($locale))
            ->category($notice->code->category())
            ->line($notice->body->in($locale));

        $summary = $notice->text?->summary();

        if ($summary instanceof LocalizedText) {
            $mail->summary($summary->in($locale));
        }

        if ($notice->link !== null) {
            $label = $notice->text?->action()?->in($locale) ?? __('notifications.entities.open_platform');
            $mail->action($label, FrontendUrl::to($notice->link));
        }

        return $mail;
    }
}

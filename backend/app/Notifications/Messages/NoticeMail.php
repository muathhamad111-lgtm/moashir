<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Data\Mail\MailNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use App\Support\FrontendUrl;
use App\Support\Mail\MailMarkdown;
use App\Support\Mail\NoticeDetails;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The e-mail of a notice: its title as the subject, its category on the band, the template's optional summary
 * line, its body as the line (or the e-mail's own subject and Markdown-lite body), a details card of the values its wording uses, the blocks the notice adds (items,
 * steps, seal) and a button when the notice links somewhere, labelled by the template's own `action` text or the
 * shared «open the platform».
 */
final class NoticeMail
{
    public static function of(object $notifiable, MailNotice $notice): BrandedMailMessage
    {
        $locale = app()->getLocale();
        $mail = BrandedMailMessage::make($notifiable, $notifiable instanceof User ? MailReason::Account : MailReason::LinkedAddress)
            ->subject(($notice->text?->emailSubject() ?? $notice->title)->in($locale))
            ->category($notice->code->category());
        foreach (self::bodyLines($notice, $locale) as $line) {
            $mail->line($line);
        }

        $summary = $notice->text?->summary();

        if ($summary instanceof LocalizedText) {
            $mail->summary($summary->in($locale));
        }

        if ($notice->text instanceof NoticeText) {
            $mail->details(NoticeDetails::of($notice->text, $locale));
        }

        $mail->blocks($notice->blocks);

        if ($notice->link !== null) {
            $label = $notice->text?->action()?->in($locale) ?? __('notifications.entities.open_platform');
            $mail->action($label, FrontendUrl::to($notice->link));
        }

        return $mail;
    }

    /**
     * The administrator's own e-mail body when written (Markdown-lite), else the notice's body as one line.
     *
     * @return list<string|Htmlable>
     */
    private static function bodyLines(MailNotice $notice, string $locale): array
    {
        $text = $notice->text;
        $template = $text?->emailBodyTemplate();

        if (! $text instanceof NoticeText || ! $template instanceof LocalizedText) {
            return [$notice->body->in($locale)];
        }

        return MailMarkdown::toLines($template->in($locale), $text->replacementsIn($locale));
    }
}

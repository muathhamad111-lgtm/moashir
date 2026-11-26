<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Support\Notifications\Templates\NotificationTextParts;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\Lang;

/**
 * Where a notice's wording lives (its translation key) and the values its variables take, so each channel can
 * render its own text: the title and body for e-mail and in-app, the `sms` part for a text message.
 */
final readonly class NoticeText
{
    private const array LOCALES = ['ar', 'en'];

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public function __construct(public string $key, public array $replacements) {}

    public function title(): LocalizedText
    {
        return LocalizedText::fromTranslationWith($this->key.'.title', $this->replacements);
    }

    public function body(): LocalizedText
    {
        return LocalizedText::fromTranslationWith($this->key.'.body', $this->replacements);
    }

    /**
     * The e-mail's own subject when an administrator wrote one, else the notice's title.
     */
    public function emailSubject(): LocalizedText
    {
        return $this->optional(NotificationTextParts::EMAIL_SUBJECT) ?? $this->title();
    }

    /**
     * The e-mail's own body (Markdown-lite) when an administrator wrote one, with its `:placeholders` left in place:
     * it is parsed first and the values are filled in afterwards as literal text (see `MailMarkdown`).
     */
    public function emailBodyTemplate(): ?LocalizedText
    {
        return $this->has(NotificationTextParts::EMAIL_BODY) ? LocalizedText::fromTranslation($this->key.'.'.NotificationTextParts::EMAIL_BODY) : null;
    }

    /**
     * @return array<string, string>
     */
    public function replacementsIn(string $locale): array
    {
        return array_map(static fn (LocalizedText|string $value): string => $value instanceof LocalizedText ? $value->in($locale) : $value, $this->replacements);
    }

    public function sms(): ?LocalizedText
    {
        return $this->optional('sms');
    }

    /**
     * The e-mail's one-line summary under the title, when the template has one.
     */
    public function summary(): ?LocalizedText
    {
        return $this->optional('summary');
    }

    /**
     * The e-mail button label, when the template sets its own instead of the shared «open the platform».
     */
    public function action(): ?LocalizedText
    {
        return $this->optional('action');
    }

    private function optional(string $part): ?LocalizedText
    {
        return $this->has($part) ? LocalizedText::fromTranslationWith($this->key.'.'.$part, $this->replacements) : null;
    }

    private function has(string $part): bool
    {
        return array_all(self::LOCALES, fn (string $locale): bool => Lang::has($this->key.'.'.$part, $locale, false));
    }

    public function templateKey(): string
    {
        return substr($this->key, strlen('notifications.'));
    }
}

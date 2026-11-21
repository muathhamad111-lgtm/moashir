<?php

declare(strict_types=1);

namespace App\Data\Notifications;

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
        foreach (self::LOCALES as $locale) {
            if (! Lang::has($this->key.'.'.$part, $locale, false)) {
                return null;
            }
        }

        return LocalizedText::fromTranslationWith($this->key.'.'.$part, $this->replacements);
    }

    public function templateKey(): string
    {
        return substr($this->key, strlen('notifications.'));
    }
}

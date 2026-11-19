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
        foreach (self::LOCALES as $locale) {
            if (! Lang::has($this->key.'.sms', $locale, false)) {
                return null;
            }
        }

        return LocalizedText::fromTranslationWith($this->key.'.sms', $this->replacements);
    }

    public function templateKey(): string
    {
        return substr($this->key, strlen('notifications.'));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Notifications\Templates;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\Translator as FileTranslator;

/**
 * Drops the cached overrides and the translator's loaded lines, so the next notification text is read again
 * (in this process and, through the queue listener, in every long-running worker).
 */
final readonly class NotificationTextsReloader
{
    public function __construct(private NotificationTextOverrides $overrides, private Translator $translator) {}

    public function reload(): void
    {
        $this->overrides->forget();
        $this->reloadTranslations();
    }

    public function reloadTranslations(): void
    {
        if ($this->translator instanceof FileTranslator) {
            $this->translator->setLoaded([]);
        }
    }
}

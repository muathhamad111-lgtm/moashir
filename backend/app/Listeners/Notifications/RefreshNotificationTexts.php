<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Services\Notifications\Templates\NotificationTextsReloader;
use Illuminate\Queue\Events\JobProcessing;

/**
 * A queue worker lives for hours and keeps loaded translations in memory; each job re-reads them so an
 * administrator's text change reaches the next notification without restarting the workers.
 */
final readonly class RefreshNotificationTexts
{
    public function __construct(private NotificationTextsReloader $reloader) {}

    public function handle(JobProcessing $event): void
    {
        $this->reloader->reloadTranslations();
    }
}

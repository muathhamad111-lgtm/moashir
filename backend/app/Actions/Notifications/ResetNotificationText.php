<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Notifications\NotificationTextOverride;
use App\Services\Notifications\Templates\NotificationTextsReloader;
use App\Support\Notifications\Templates\NotificationTextTemplate;

final readonly class ResetNotificationText
{
    public function __construct(private NotificationTextsReloader $reloader) {}

    public function handle(NotificationTextTemplate $template): void
    {
        NotificationTextOverride::query()->where('template_key', $template->key)->first()?->delete();
        $this->reloader->reload();
    }
}

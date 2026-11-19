<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Support\Notifications\Templates\NotificationTextTemplate;

final readonly class NotificationTextChange
{
    /**
     * @param  array<string, array{ar: string, en: string}>  $parts
     */
    public function __construct(public NotificationTextTemplate $template, public array $parts) {}
}

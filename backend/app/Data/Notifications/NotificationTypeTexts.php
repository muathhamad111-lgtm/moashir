<?php

declare(strict_types=1);

namespace App\Data\Notifications;

final readonly class NotificationTypeTexts
{
    /**
     * @param  list<NotificationTextView>  $texts
     * @param  list<NotificationChannelState>  $channels
     */
    public function __construct(public NotificationType $type, public array $texts, public array $channels) {}
}

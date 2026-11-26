<?php

declare(strict_types=1);

namespace App\Data\Notifications;

final readonly class NotificationPreferenceRow
{
    /**
     * @param  list<ChannelPreference>  $channels
     */
    public function __construct(public NotificationType $type, public array $channels) {}
}

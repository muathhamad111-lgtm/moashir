<?php

declare(strict_types=1);

namespace App\Services\Demo;

use Illuminate\Notifications\ChannelManager;

final class DiscardedNotifications extends ChannelManager
{
    public function send(mixed $notifiables, mixed $notification): void {}

    /**
     * @param  list<string>|null  $channels
     */
    public function sendNow(mixed $notifiables, mixed $notification, ?array $channels = null): void {}
}

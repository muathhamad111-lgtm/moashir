<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Models\Identity\User;
use App\Notifications\Contracts\HasNotificationCode;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\NotificationTypeCatalog;
use Illuminate\Notifications\Events\NotificationSending;

final readonly class SkipDisabledDeliveryChannel
{
    public function __construct(private NotificationTypeCatalog $catalog, private NotificationPreferences $preferences) {}

    public function handle(NotificationSending $event): ?bool
    {
        $channel = NotificationChannel::fromDeliveryChannel($event->channel);

        if (! $event->notifiable instanceof User || ! $event->notification instanceof HasNotificationCode || ! $channel instanceof NotificationChannel) {
            return null;
        }

        $code = $event->notification->notificationCode();

        if (! $this->catalog->of($code)->allowsOptOut($channel)) {
            return null;
        }

        return $this->preferences->isDisabled($event->notifiable->id, $code, $channel) ? false : null;
    }
}

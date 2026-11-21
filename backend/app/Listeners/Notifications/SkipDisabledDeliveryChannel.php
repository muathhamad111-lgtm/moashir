<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Contracts\HasNotificationCode;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\SystemNotificationChannels;
use App\Services\Settings\GeneralEmailNotifications;
use Illuminate\Notifications\Events\NotificationSending;

final readonly class SkipDisabledDeliveryChannel
{
    public function __construct(
        private NotificationTypeCatalog $catalog,
        private NotificationPreferences $preferences,
        private GeneralEmailNotifications $generalEmail,
        private SystemNotificationChannels $systemChannels,
    ) {}

    public function handle(NotificationSending $event): ?bool
    {
        $channel = NotificationChannel::fromDeliveryChannel($event->channel);

        if (! $event->notification instanceof HasNotificationCode || ! $channel instanceof NotificationChannel) {
            return null;
        }

        $code = $event->notification->notificationCode();

        if ($this->systemChannels->isSwitchedOff($code, $channel)) {
            return false;
        }

        return $event->notifiable instanceof User ? $this->forRecipient($event->notifiable, $code, $channel) : null;
    }

    private function forRecipient(User $recipient, NotificationCode $code, NotificationChannel $channel): ?bool
    {
        if (! $this->catalog->of($code)->allowsOptOut($channel)) {
            return null;
        }

        if ($channel === NotificationChannel::Email && ! $this->generalEmail->isEnabled()) {
            return false;
        }

        return $this->preferences->isDisabled($recipient->id, $code, $channel) ? false : null;
    }
}

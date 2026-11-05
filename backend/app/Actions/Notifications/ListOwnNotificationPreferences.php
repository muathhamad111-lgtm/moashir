<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\ChannelPreference;
use App\Data\Notifications\NotificationPreferenceRow;
use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationChannel;
use App\Models\Identity\User;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\UserNotificationAudiences;

final readonly class ListOwnNotificationPreferences
{
    public function __construct(
        private NotificationTypeCatalog $catalog,
        private UserNotificationAudiences $audiences,
        private NotificationPreferences $preferences,
    ) {}

    /**
     * @return list<NotificationPreferenceRow>
     */
    public function handle(User $user): array
    {
        $choices = $this->preferences->choicesOf($user->id);

        return array_map(
            static fn (NotificationType $type): NotificationPreferenceRow => new NotificationPreferenceRow($type, array_map(
                static fn (NotificationChannel $channel): ChannelPreference => new ChannelPreference(
                    $channel,
                    ! $type->allowsOptOut($channel) || ($choices[NotificationPreferences::key($type->code, $channel)] ?? true),
                    $type->allowsOptOut($channel),
                ),
                $type->channels,
            )),
            $this->catalog->receivableBy($this->audiences->of($user)),
        );
    }
}

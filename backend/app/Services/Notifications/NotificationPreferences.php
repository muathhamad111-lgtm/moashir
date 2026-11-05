<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Notifications\NotificationPreference;

final class NotificationPreferences
{
    public function isDisabled(int $userId, NotificationCode $code, NotificationChannel $channel): bool
    {
        return NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('code', $code)
            ->where('channel', $channel)
            ->where('is_enabled', false)
            ->exists();
    }

    /**
     * @return array<string, bool>
     */
    public function choicesOf(int $userId): array
    {
        $choices = [];

        foreach (NotificationPreference::query()->where('user_id', $userId)->get(['code', 'channel', 'is_enabled']) as $preference) {
            $choices[self::key($preference->code, $preference->channel)] = $preference->is_enabled;
        }

        return $choices;
    }

    public static function key(NotificationCode $code, NotificationChannel $channel): string
    {
        return $code->value.'|'.$channel->value;
    }
}

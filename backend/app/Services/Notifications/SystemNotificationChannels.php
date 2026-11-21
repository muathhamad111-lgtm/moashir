<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Notifications\NotificationChannelSetting;
use Illuminate\Contracts\Cache\Repository;

/**
 * The System Administrator's channel choices per notification type, laid over the shipped definitions:
 * a mandatory type keeps its shipped channels; any other type may have the in-app or e-mail channel
 * switched off, or switched on when it does not ship with it.
 */
final readonly class SystemNotificationChannels
{
    public const string CACHE_KEY = 'notifications.channel-settings';

    public const array SWITCHABLE = [NotificationChannel::InApp, NotificationChannel::Email, NotificationChannel::Sms];

    public function __construct(private Repository $cache, private NotificationTypeCatalog $catalog) {}

    public function isEnabled(NotificationType $type, NotificationChannel $channel): bool
    {
        return $this->choiceFor($type, $channel) ?? in_array($channel, $type->channels, true);
    }

    /**
     * Only an explicit choice counts: a type nobody touched keeps sending exactly as its senders decide.
     */
    public function isSwitchedOff(NotificationCode $code, NotificationChannel $channel): bool
    {
        return $this->choiceFor($this->catalog->of($code), $channel) === false;
    }

    public function isSwitchedOn(NotificationCode $code, NotificationChannel $channel): bool
    {
        return $this->choiceFor($this->catalog->of($code), $channel) === true;
    }

    public function isSwitchable(NotificationType $type, NotificationChannel $channel): bool
    {
        return ! $type->isMandatory && in_array($channel, self::SWITCHABLE, true);
    }

    /**
     * @return list<NotificationChannel>
     */
    public function enabledOf(NotificationType $type): array
    {
        return array_values(array_filter(NotificationChannel::cases(), fn (NotificationChannel $channel): bool => $this->isEnabled($type, $channel)));
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function choiceFor(NotificationType $type, NotificationChannel $channel): ?bool
    {
        return $this->isSwitchable($type, $channel) ? ($this->choices()[self::key($type->code, $channel)] ?? null) : null;
    }

    /**
     * @return array<string, bool>
     */
    private function choices(): array
    {
        /** @var array<string, bool> */
        return $this->cache->rememberForever(self::CACHE_KEY, static fn (): array => NotificationChannelSetting::query()
            ->get(['code', 'channel', 'is_enabled'])
            ->mapWithKeys(static fn (NotificationChannelSetting $setting): array => [self::key($setting->code, $setting->channel) => $setting->is_enabled])
            ->all());
    }

    private static function key(NotificationCode $code, NotificationChannel $channel): string
    {
        return $code->value.'|'.$channel->value;
    }
}

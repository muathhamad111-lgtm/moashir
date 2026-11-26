<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Notifications\NotificationChannelSetting;
use Illuminate\Contracts\Cache\Repository;

/**
 * The System Administrator's channel choices per notification type and audience, laid over the shipped
 * definitions: a core type keeps its shipped channels; any other type may have a channel switched off for the
 * beneficiary or for management, or switched on when it does not ship with it.
 */
final readonly class SystemNotificationChannels
{
    public const string CACHE_KEY = 'notifications.channel-settings';

    public const array SWITCHABLE = [NotificationChannel::InApp, NotificationChannel::Email, NotificationChannel::Sms];

    public function __construct(private Repository $cache, private NotificationTypeCatalog $catalog) {}

    public function isEnabled(NotificationType $type, NotificationChannel $channel, NotificationAudienceKind $audience): bool
    {
        return $this->choiceFor($type, $channel, $audience) ?? in_array($channel, $type->channels, true);
    }

    /**
     * Only an explicit choice counts: a type nobody touched keeps sending exactly as its senders decide.
     */
    public function isSwitchedOff(NotificationCode $code, NotificationChannel $channel, NotificationAudienceKind $audience): bool
    {
        return $this->choiceFor($this->catalog->of($code), $channel, $audience) === false;
    }

    public function isSwitchedOn(NotificationCode $code, NotificationChannel $channel, NotificationAudienceKind $audience): bool
    {
        return $this->choiceFor($this->catalog->of($code), $channel, $audience) === true;
    }

    public function isSwitchable(NotificationType $type, NotificationChannel $channel): bool
    {
        return ! $type->isCore && in_array($channel, self::SWITCHABLE, true);
    }

    /**
     * The channels a type sends by for at least one of its audiences.
     *
     * @return list<NotificationChannel>
     */
    public function enabledOf(NotificationType $type): array
    {
        return array_values(array_filter(NotificationChannel::cases(), fn (NotificationChannel $channel): bool => array_filter(
            $type->audienceKinds(),
            fn (NotificationAudienceKind $audience): bool => $this->isEnabled($type, $channel, $audience),
        ) !== []));
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function choiceFor(NotificationType $type, NotificationChannel $channel, NotificationAudienceKind $audience): ?bool
    {
        return $this->isSwitchable($type, $channel) ? ($this->choices()[self::key($type->code, $channel, $audience)] ?? null) : null;
    }

    /**
     * @return array<string, bool>
     */
    private function choices(): array
    {
        /** @var array<string, bool> */
        return $this->cache->rememberForever(self::CACHE_KEY, static fn (): array => NotificationChannelSetting::query()
            ->get(['code', 'channel', 'audience', 'is_enabled'])
            ->mapWithKeys(static fn (NotificationChannelSetting $setting): array => [self::key($setting->code, $setting->channel, $setting->audience) => $setting->is_enabled])
            ->all());
    }

    private static function key(NotificationCode $code, NotificationChannel $channel, NotificationAudienceKind $audience): string
    {
        return $code->value.'|'.$channel->value.'|'.$audience->value;
    }
}

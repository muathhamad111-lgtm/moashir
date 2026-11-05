<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

use App\Notifications\Channels\InAppChannel;
use App\Notifications\Channels\SmsChannel;
use ValueError;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case Sms = 'sms';

    public static function fromDeliveryChannel(string $channel): ?self
    {
        return match ($channel) {
            'mail' => self::Email,
            SmsChannel::class => self::Sms,
            InAppChannel::class => self::InApp,
            default => null,
        };
    }

    public static function fromLetter(string $letter): self
    {
        return match ($letter) {
            'I' => self::InApp,
            'E' => self::Email,
            'S' => self::Sms,
            default => throw new ValueError("Unknown notification channel letter [{$letter}]."),
        };
    }

    public function isOptional(): bool
    {
        return $this !== self::InApp;
    }

    /**
     * @return list<string>
     */
    public static function optionalValues(): array
    {
        return [self::Email->value, self::Sms->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

use App\Models\Identity\User;

/**
 * The two audiences an administrator writes for and switches channels for: the beneficiary (an entity and its
 * users, or a guest who wrote to the platform) and management (the platform's staff). Anyone who is not a
 * staff user — an entity user or an address without an account — reads as the beneficiary.
 */
enum NotificationAudienceKind: string
{
    case Beneficiary = 'beneficiary';
    case Management = 'management';

    public static function of(mixed $notifiable): self
    {
        return $notifiable instanceof User && ! $notifiable->loadMissing('roles')->isEntityUser() ? self::Management : self::Beneficiary;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

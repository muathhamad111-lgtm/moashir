<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum CommitteeMembershipStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Excluded = 'excluded';
    case Substituted = 'substituted';
    case Removed = 'removed';

    public function canWork(): bool
    {
        return $this === self::Active;
    }

    public function isSeated(): bool
    {
        return $this !== self::Removed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

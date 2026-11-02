<?php

declare(strict_types=1);

namespace App\Enums\Award;

use App\Enums\Identity\RoleKey;

enum CycleMemberRole: string
{
    case FilteringOfficer = 'filtering_officer';
    case AwardSecretary = 'award_secretary';

    public function roleKey(): RoleKey
    {
        return match ($this) {
            self::FilteringOfficer => RoleKey::FilteringOfficer,
            self::AwardSecretary => RoleKey::AwardSecretary,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

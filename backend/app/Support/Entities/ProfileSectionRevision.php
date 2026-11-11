<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Enums\Entities\ProfileSection;

final class ProfileSectionRevision
{
    private const int LENGTH = 32;

    public static function of(ProfileFieldValues $values, ProfileSection $section): string
    {
        $canonical = json_encode($values->editableValuesOf($section), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return substr(hash('sha256', $section->value.':'.$canonical), 0, self::LENGTH);
    }
}

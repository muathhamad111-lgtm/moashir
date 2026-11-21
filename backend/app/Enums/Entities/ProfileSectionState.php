<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileSectionState: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';

    public static function withMissing(int $missingCount): self
    {
        return $missingCount === 0 ? self::Complete : self::Incomplete;
    }
}

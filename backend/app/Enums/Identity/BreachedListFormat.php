<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Support\ValueObjects\PasswordDigest;

enum BreachedListFormat: string
{
    case Plain = 'plain';
    case Sha256 = 'sha256';

    public function digestOf(string $entry): ?PasswordDigest
    {
        return match ($this) {
            self::Plain => PasswordDigest::ofPassword($entry),
            self::Sha256 => PasswordDigest::tryFromHex($entry),
        };
    }
}

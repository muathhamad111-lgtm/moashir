<?php

declare(strict_types=1);

namespace App\Support\Identity;

use App\Enums\Identity\IdentityField;
use App\Support\ValueObjects\NationalId;

final class IdentityValueMask
{
    public static function of(IdentityField $field, ?string $value): ?string
    {
        if ($value === null || $field === IdentityField::Name) {
            return $value;
        }

        return NationalId::tryFrom($value)?->masked();
    }
}

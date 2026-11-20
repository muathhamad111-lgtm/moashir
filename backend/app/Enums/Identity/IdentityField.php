<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum IdentityField: string
{
    case Name = 'name';
    case NationalId = 'national_id';
}

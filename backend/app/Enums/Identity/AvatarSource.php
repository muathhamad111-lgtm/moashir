<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum AvatarSource: string
{
    case Photo = 'photo';
    case EntityLogo = 'entity_logo';
}

<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum SessionGroup: string
{
    case SuperAdmin = 'super_admin';
    case Staff = 'staff';
    case Entity = 'entity';
}

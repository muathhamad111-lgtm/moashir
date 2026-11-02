<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum EntityAccess: string
{
    case Full = 'full';
    case Contact = 'contact';
    case None = 'none';
}

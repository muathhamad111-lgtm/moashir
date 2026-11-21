<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum NationalIdState: string
{
    case NotRequired = 'not_required';
    case Missing = 'missing';
    case Pending = 'pending';
    case Provided = 'provided';
}

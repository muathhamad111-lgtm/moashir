<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum LoginMode: string
{
    case Password = 'password';
    case Otp = 'otp';
}

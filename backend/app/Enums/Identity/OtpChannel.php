<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum OtpChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
}

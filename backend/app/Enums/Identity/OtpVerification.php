<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum OtpVerification
{
    case Verified;
    case InvalidCode;
    case AttemptsExceeded;
    case Expired;
    case Closed;
    case Unknown;
}

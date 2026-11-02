<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum SmsPurpose: string
{
    case Otp = 'otp';
    case Notification = 'notification';
    case ConnectionTest = 'connection_test';
}

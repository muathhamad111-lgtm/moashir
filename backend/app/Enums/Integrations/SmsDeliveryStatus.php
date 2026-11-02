<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum SmsDeliveryStatus: string
{
    case Sent = 'sent';
    case SuppressedTestMode = 'suppressed_test_mode';
}

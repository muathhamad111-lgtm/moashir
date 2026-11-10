<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum ApplyBlock: string
{
    case OpenRequestExists = 'open_request_exists';
    case AwaitingPayment = 'awaiting_payment';
    case PathUnavailable = 'path_unavailable';
}

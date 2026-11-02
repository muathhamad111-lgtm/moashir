<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum FileScanAttempt: string
{
    case Settled = 'settled';
    case ScannerUnavailable = 'scanner_unavailable';
}

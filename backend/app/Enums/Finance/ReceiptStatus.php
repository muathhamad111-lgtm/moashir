<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum ReceiptStatus: string
{
    case Issued = 'issued';
    case Voided = 'voided';
}

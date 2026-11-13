<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum BankAccountState: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}

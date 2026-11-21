<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum BankAccountChange: string
{
    case Added = 'added';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case Deleted = 'deleted';
}

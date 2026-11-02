<?php

declare(strict_types=1);

namespace App\Contracts\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Support\ValueObjects\Money;

interface ServicePriceSource
{
    public function priceBeforeVatOf(PaymentPurpose $purpose): ?Money;
}

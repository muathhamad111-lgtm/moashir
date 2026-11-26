<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Contracts\Finance\ServicePriceSource;
use App\Enums\Finance\PaymentPurpose;
use App\Support\ValueObjects\Money;

final readonly class VersionedServicePriceSource implements ServicePriceSource
{
    public function __construct(private PriceVersions $versions) {}

    public function priceBeforeVatOf(PaymentPurpose $purpose): ?Money
    {
        return $this->versions->inForce($purpose)?->price();
    }
}

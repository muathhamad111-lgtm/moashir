<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Finance\ServicePriceSource;
use App\Data\Finance\PriceQuote;
use App\Enums\Finance\PaymentPurpose;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;

final readonly class ServicePriceQuotes
{
    public function __construct(private ServicePriceSource $prices) {}

    public function quoteOf(PaymentPurpose $purpose, VatRate $vatRate): ?PriceQuote
    {
        $price = $this->prices->priceBeforeVatOf($purpose);

        return $price instanceof Money ? PriceQuote::of($price, $vatRate) : null;
    }
}

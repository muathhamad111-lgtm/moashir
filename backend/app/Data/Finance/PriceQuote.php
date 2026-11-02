<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;

final readonly class PriceQuote
{
    public function __construct(
        public Money $priceBeforeVat,
        public VatRate $vatRate,
        public Money $vatAmount,
        public Money $total,
    ) {}

    public static function of(Money $priceBeforeVat, VatRate $vatRate): self
    {
        $vat = $vatRate->of($priceBeforeVat);

        return new self($priceBeforeVat, $vatRate, $vat, $priceBeforeVat->plus($vat));
    }
}

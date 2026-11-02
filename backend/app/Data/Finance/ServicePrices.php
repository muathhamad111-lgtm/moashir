<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class ServicePrices
{
    public function __construct(public ?PriceQuote $accredited, public ?PriceQuote $bag) {}
}

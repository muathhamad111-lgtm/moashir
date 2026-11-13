<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Data\Paths\PathPricing;

final readonly class ServicePrices
{
    /**
     * @param  array<string, PathPricing>  $paths
     */
    public function __construct(public ?PriceQuote $accredited, public ?PriceQuote $bag, public array $paths) {}
}

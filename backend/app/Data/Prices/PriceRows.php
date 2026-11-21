<?php

declare(strict_types=1);

namespace App\Data\Prices;

use App\Support\ValueObjects\VatRate;

final readonly class PriceRows
{
    /**
     * @param  list<PriceRow>  $rows
     */
    public function __construct(public array $rows, public VatRate $vatRate, public PriceEditWindow $window) {}
}

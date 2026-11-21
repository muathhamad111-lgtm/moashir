<?php

declare(strict_types=1);

namespace App\Data\Prices;

use App\Enums\Prices\PriceVersionState;
use App\Models\Finance\ServicePriceVersion;

final readonly class PriceHistoryItem
{
    public function __construct(public ServicePriceVersion $version, public PriceVersionState $state) {}
}

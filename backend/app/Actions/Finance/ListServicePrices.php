<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\ServicePrices;
use App\Enums\Finance\PaymentPurpose;
use App\Services\Paths\PathPricingReader;

final readonly class ListServicePrices
{
    public function __construct(private PathPricingReader $pricing) {}

    public function handle(): ServicePrices
    {
        $paths = $this->pricing->allPaths();

        return new ServicePrices($paths[PaymentPurpose::Accredited->value]->price, $paths[PaymentPurpose::Bag->value]->price, $paths);
    }
}

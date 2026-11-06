<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Integrations\IntegrationMode;

final readonly class OrderTerms
{
    public function __construct(
        public PaymentPurpose $purpose,
        public PaymentChannel $channel,
        public PriceQuote $quote,
        public IntegrationMode $mode,
        public string $idempotencyKey,
    ) {}
}

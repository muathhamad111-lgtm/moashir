<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationMode;
use App\Support\ValueObjects\Money;

final readonly class PaymentCheckoutRequest
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public IntegrationMode $mode,
        public Money $amount,
        public string $description,
        public string $callbackUrl,
        public array $metadata = [],
    ) {}
}

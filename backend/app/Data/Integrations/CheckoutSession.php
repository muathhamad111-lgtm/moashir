<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationMode;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\SecretValue;
use Carbon\CarbonImmutable;

final readonly class CheckoutSession
{
    public function __construct(
        public IntegrationMode $mode,
        public string $intentId,
        public SecretValue $clientSecret,
        public string $status,
        public Money $amount,
        public ?CarbonImmutable $expiresAt,
    ) {}
}

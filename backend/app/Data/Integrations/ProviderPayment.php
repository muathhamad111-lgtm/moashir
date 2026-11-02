<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Support\ValueObjects\Money;

final readonly class ProviderPayment
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $id,
        public string $status,
        public Money $amount,
        public ?string $sourceType,
        public ?string $cardCompany,
        public ?string $cardLast4,
        public ?string $responseCode,
        public ?string $message,
        public array $metadata,
    ) {}

    public function metadataValue(string $key): ?string
    {
        return $this->metadata[$key] ?? null;
    }
}

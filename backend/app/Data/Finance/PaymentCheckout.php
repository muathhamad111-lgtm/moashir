<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Integrations\CheckoutPattern;
use App\Enums\Integrations\IntegrationMode;
use App\Support\ValueObjects\SecretValue;
use Carbon\CarbonInterface;

final readonly class PaymentCheckout
{
    /**
     * @param  list<string>  $methods
     * @param  list<string>  $supportedNetworks
     * @param  array<string, string>  $metadata
     * @param  array{country: string, label: string, validate_merchant_url: string}|null  $applePay
     */
    public function __construct(
        public CheckoutPattern $pattern,
        public IntegrationMode $mode,
        public string $publishableKey,
        public int $amountHalalas,
        public string $currency,
        public string $description,
        public string $callbackUrl,
        public string $language,
        public array $methods,
        public array $supportedNetworks,
        public array $metadata,
        public string $intentId,
        public SecretValue $clientSecret,
        public ?array $applePay,
        public CarbonInterface $expiresAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pattern' => $this->pattern->value,
            'mode' => $this->mode->value,
            'publishable_key' => $this->publishableKey,
            'amount_halalas' => $this->amountHalalas,
            'currency' => $this->currency,
            'description' => $this->description,
            'callback_url' => $this->callbackUrl,
            'language' => $this->language,
            'methods' => $this->methods,
            'supported_networks' => $this->supportedNetworks,
            'metadata' => $this->metadata,
            'payment_intent' => ['id' => $this->intentId, 'client_secret' => $this->clientSecret->reveal()],
            'apple_pay' => $this->applePay,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}

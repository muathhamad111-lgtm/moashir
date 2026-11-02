<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmsDeliveryStatus;

final readonly class SmsSendResult
{
    public function __construct(
        public ?IntegrationProvider $provider,
        public SmsDeliveryStatus $status,
        public ?string $providerMessageId = null,
        public ?int $segments = null,
    ) {}

    public static function sent(IntegrationProvider $provider, ?string $providerMessageId, ?int $segments = null): self
    {
        return new self($provider, SmsDeliveryStatus::Sent, $providerMessageId, $segments);
    }

    public static function suppressedInTestMode(?IntegrationProvider $provider): self
    {
        return new self($provider, SmsDeliveryStatus::SuppressedTestMode);
    }
}

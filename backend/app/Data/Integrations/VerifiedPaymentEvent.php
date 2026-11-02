<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationMode;

final readonly class VerifiedPaymentEvent
{
    /**
     * @param  array<array-key, mixed>  $redactedPayload
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public IntegrationMode $mode,
        public ?string $providerPaymentId,
        public ?string $moashirPaymentId,
        public array $redactedPayload,
    ) {}
}

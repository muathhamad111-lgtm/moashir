<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;

final readonly class ConnectionTestResult
{
    /**
     * @param  list<ConnectionCheck>  $checks
     * @param  array<string, scalar|null>  $info
     */
    public function __construct(
        public IntegrationProvider $provider,
        public IntegrationMode $mode,
        public int $latencyMs,
        public array $checks,
        public array $info = [],
        public ?ProviderErrorKind $errorKind = null,
    ) {}

    public function ok(): bool
    {
        return ! $this->errorKind instanceof ProviderErrorKind;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'provider' => $this->provider->value,
            'mode' => $this->mode->value,
            'latency_ms' => $this->latencyMs,
            'checks' => array_map(static fn (ConnectionCheck $check): array => $check->toArray(), $this->checks),
            'info' => (object) $this->info,
            'error_kind' => $this->errorKind?->value,
        ];
    }
}

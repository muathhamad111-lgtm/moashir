<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use RuntimeException;

final class ProviderCallFailed extends RuntimeException
{
    private function __construct(
        public readonly IntegrationProvider $provider,
        public readonly string $operation,
        public readonly ProviderErrorKind $kind,
        public readonly ?int $httpStatus,
        public readonly ?string $providerCode,
    ) {
        parent::__construct(sprintf('The %s call %s failed: %s.', $provider->value, $operation, $kind->value));
    }

    public static function because(
        IntegrationProvider $provider,
        string $operation,
        ProviderErrorKind $kind,
        ?int $httpStatus = null,
        ?string $providerCode = null,
    ): self {
        return new self($provider, $operation, $kind, $httpStatus, $providerCode === null ? null : mb_substr($providerCode, 0, 60));
    }

    public function isTransient(): bool
    {
        return $this->kind->isTransient();
    }
}

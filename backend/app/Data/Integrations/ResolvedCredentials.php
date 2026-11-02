<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SecretValue;
use LogicException;

final readonly class ResolvedCredentials
{
    /**
     * @param  array<string, mixed>  $publicConfig
     * @param  array<string, SecretValue>  $secrets
     * @param  array<string, mixed>  $providerRefs
     */
    public function __construct(
        public IntegrationKind $kind,
        public ?IntegrationProvider $provider,
        public IntegrationMode $mode,
        public CredentialSource $source,
        public array $publicConfig = [],
        private array $secrets = [],
        public array $providerRefs = [],
        public ?int $credentialId = null,
    ) {}

    public static function none(IntegrationKind $kind, ?IntegrationProvider $provider, IntegrationMode $mode): self
    {
        return new self($kind, $provider, $mode, CredentialSource::None);
    }

    public function isConfigured(): bool
    {
        return $this->source !== CredentialSource::None && $this->provider instanceof IntegrationProvider;
    }

    public function provider(): IntegrationProvider
    {
        return $this->provider ?? throw new LogicException('No provider is configured for '.$this->kind->value.'.');
    }

    public function secret(string $field): SecretValue
    {
        return $this->secrets[$field] ?? throw new LogicException('The secret '.$field.' is not configured.');
    }

    public function hasSecret(string $field): bool
    {
        return isset($this->secrets[$field]);
    }

    /**
     * @return list<string>
     */
    public function secretFieldNames(): array
    {
        return array_keys($this->secrets);
    }

    public function string(string $field, string $default = ''): string
    {
        return ScalarValue::string($this->publicConfig[$field] ?? null, $default);
    }

    public function int(string $field, int $default): int
    {
        return ScalarValue::int($this->publicConfig[$field] ?? null, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'kind' => $this->kind->value,
            'provider' => $this->provider?->value,
            'mode' => $this->mode->value,
            'source' => $this->source->value,
            'secrets' => array_keys($this->secrets),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('Resolved credentials are never serialized.');
    }
}

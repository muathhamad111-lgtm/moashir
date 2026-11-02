<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationMode;
use App\Enums\Settings\SettingKey;

final readonly class IntegrationUpdate
{
    /**
     * @param  list<ProviderCredentialChanges>  $credentials
     * @param  array<string, mixed>  $settings
     * @param  array<string, bool>  $confirmations
     */
    public function __construct(
        public ?IntegrationMode $mode = null,
        public ?ActivationRequest $activation = null,
        public array $credentials = [],
        public array $settings = [],
        public array $confirmations = [],
        public ?string $reason = null,
    ) {}

    public function touchesSecrets(): bool
    {
        return array_any($this->credentials, static fn (ProviderCredentialChanges $changes): bool => $changes->changes->touchesSecrets());
    }

    public function hasSetting(SettingKey $key): bool
    {
        return array_key_exists($key->value, $this->settings);
    }

    public function setting(SettingKey $key): mixed
    {
        return $this->settings[$key->value] ?? null;
    }

    public function withSetting(SettingKey $key, mixed $value): self
    {
        return new self($this->mode, $this->activation, $this->credentials, [...$this->settings, $key->value => $value], $this->confirmations, $this->reason);
    }

    public function hasConfirmation(string $key): bool
    {
        return array_key_exists($key, $this->confirmations);
    }
}

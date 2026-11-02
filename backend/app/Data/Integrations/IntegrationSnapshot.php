<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Settings\SettingKey;
use App\Models\Integrations\IntegrationCredential;

final readonly class IntegrationSnapshot
{
    /**
     * @param  list<IntegrationCredential>  $rows
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $actorNames
     */
    public function __construct(
        public IntegrationKind $kind,
        public CredentialSource $source,
        public array $rows,
        public ?ResolvedCredentials $environment,
        public array $settings,
        public array $actorNames,
        public bool $isProduction,
    ) {}

    public function row(IntegrationProvider $provider, ?IntegrationMode $mode = null): ?IntegrationCredential
    {
        foreach ($this->rows as $row) {
            if ($row->provider === $provider && (! $mode instanceof IntegrationMode || $row->mode === $mode)) {
                return $row;
            }
        }

        return null;
    }

    public function activeRow(): ?IntegrationCredential
    {
        foreach ($this->rows as $row) {
            if ($row->is_active) {
                return $row;
            }
        }

        return null;
    }

    public function environmentFor(IntegrationProvider $provider, ?IntegrationMode $mode = null): ?ResolvedCredentials
    {
        $environment = $this->environment;

        if (! $environment instanceof ResolvedCredentials || $environment->provider !== $provider) {
            return null;
        }

        return ! $mode instanceof IntegrationMode || $environment->mode === $mode ? $environment : null;
    }

    public function mode(): IntegrationMode
    {
        $mode = $this->setting(SettingKey::modeOf($this->kind));

        return IntegrationMode::tryFrom(is_string($mode) ? $mode : '') ?? IntegrationMode::Live;
    }

    public function isActive(): bool
    {
        return $this->activeRow() instanceof IntegrationCredential || $this->environment instanceof ResolvedCredentials;
    }

    public function activeProvider(): ?IntegrationProvider
    {
        return $this->activeRow()->provider ?? $this->environment?->provider;
    }

    public function setting(SettingKey $key): mixed
    {
        return array_key_exists($key->value, $this->settings) ? $this->settings[$key->value] : $key->defaultValue();
    }

    public function actorName(int $userId): ?string
    {
        return $this->actorNames[$userId] ?? null;
    }
}

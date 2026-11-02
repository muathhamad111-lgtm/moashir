<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Settings\IntegrationSettings;
use App\Support\Integrations\CredentialCompleteness;
use App\Support\ValueObjects\SecretValue;

final readonly class IntegrationCredentialsResolver
{
    public function __construct(
        private EnvironmentCredentials $environment,
        private IntegrationSettings $settings,
    ) {}

    public function resolve(IntegrationKind $kind): ResolvedCredentials
    {
        $mode = $this->settings->mode($kind);

        if (! $this->hasRows($kind)) {
            return $this->environment->for($kind) ?? ResolvedCredentials::none($kind, null, $mode);
        }

        $active = IntegrationCredential::query()->ofKind($kind)->where('is_active', true)->first();

        return $active instanceof IntegrationCredential
            ? $this->fromRow($active)
            : ResolvedCredentials::none($kind, null, $mode);
    }

    public function forProvider(IntegrationProvider $provider, ?IntegrationMode $mode = null): ResolvedCredentials
    {
        $kind = $provider->kind();
        $mode ??= $this->settings->mode($kind);

        if (! $this->hasRows($kind)) {
            $environment = $this->environment->for($kind);

            return $environment instanceof ResolvedCredentials && $environment->provider === $provider && $environment->mode === $mode
                ? $environment
                : ResolvedCredentials::none($kind, $provider, $mode);
        }

        $row = IntegrationCredential::query()
            ->ofKind($kind)
            ->where('provider', $provider->value)
            ->when($kind->hasModePerCredential(), static fn ($query) => $query->where('mode', $mode->value))
            ->first();

        return $row instanceof IntegrationCredential ? $this->fromRow($row) : ResolvedCredentials::none($kind, $provider, $mode);
    }

    public function draftFor(IntegrationProvider $provider): ResolvedCredentials
    {
        $complete = $this->forProvider($provider);

        if ($complete->isConfigured()) {
            return $complete;
        }

        $row = IntegrationCredential::query()->ofKind($provider->kind())->where('provider', $provider->value)->first();

        return $row instanceof IntegrationCredential ? $this->credentialsOf($row) : $complete;
    }

    public function fromRow(IntegrationCredential $row): ResolvedCredentials
    {
        if (! $this->isComplete($row)) {
            return ResolvedCredentials::none($row->kind, $row->provider, $row->mode);
        }

        return $this->credentialsOf($row);
    }

    private function credentialsOf(IntegrationCredential $row): ResolvedCredentials
    {
        $secrets = [];

        foreach ($row->secrets ?? [] as $field => $value) {
            if ($value !== '') {
                $secrets[$field] = new SecretValue($value);
            }
        }

        return new ResolvedCredentials(
            $row->kind,
            $row->provider,
            $row->mode,
            CredentialSource::Settings,
            $row->public_config,
            $secrets,
            $row->provider_refs,
            $row->id,
        );
    }

    public function isComplete(IntegrationCredential $row): bool
    {
        return CredentialCompleteness::of($row);
    }

    private function hasRows(IntegrationKind $kind): bool
    {
        return IntegrationCredential::query()->ofKind($kind)->exists();
    }
}

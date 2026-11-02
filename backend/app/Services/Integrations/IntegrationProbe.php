<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\IntegrationConnectionTester;
use App\Data\Integrations\ConnectionCheck;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Identity\User;

final readonly class IntegrationProbe
{
    public function __construct(
        private IntegrationCredentialsResolver $resolver,
        private ProviderAdapterRegistry $adapters,
        private ConnectionTestRecorder $recorder,
    ) {}

    public function test(IntegrationProvider $provider, ?IntegrationMode $mode, User $actor): ConnectionTestResult
    {
        $credentials = $this->configured($provider, $mode);
        $result = $this->run($credentials);

        $this->recorder->record($credentials, $result, $actor);

        if (! $result->ok() && $result->errorKind instanceof ProviderErrorKind) {
            throw IntegrationRejected::providerError($result->errorKind, $result);
        }

        return $result;
    }

    public function configured(IntegrationProvider $provider, ?IntegrationMode $mode = null): ResolvedCredentials
    {
        $credentials = $this->resolver->forProvider($provider, $mode);

        if (! $credentials->isConfigured()) {
            throw IntegrationRejected::notConfigured($provider->kind());
        }

        return $credentials;
    }

    public function configuredKey(IntegrationProvider $provider, string $secretField): ResolvedCredentials
    {
        $credentials = $this->resolver->draftFor($provider);

        if (! $credentials->hasSecret($secretField)) {
            throw IntegrationRejected::notConfigured($provider->kind());
        }

        return $credentials;
    }

    private function run(ResolvedCredentials $credentials): ConnectionTestResult
    {
        $startedAt = (int) hrtime(true);

        try {
            return $this->adapters->get(IntegrationConnectionTester::class, $credentials->provider())->test($credentials);
        } catch (ProviderCallFailed $failure) {
            return new ConnectionTestResult(
                $credentials->provider(),
                $credentials->mode,
                ProviderHttp::elapsedMilliseconds($startedAt),
                [ConnectionCheck::failed('connection', $failure->kind->value)],
                [],
                $failure->kind,
            );
        }
    }
}

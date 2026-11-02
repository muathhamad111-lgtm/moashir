<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Contracts\Integrations\AiModelCatalog;
use App\Data\Integrations\AiModelInfo;
use App\Enums\Integrations\IntegrationProvider;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\ProviderAdapterRegistry;

final readonly class ListAiModels
{
    public function __construct(private IntegrationProbe $probe, private ProviderAdapterRegistry $adapters) {}

    /**
     * @return list<AiModelInfo>
     */
    public function handle(IntegrationProvider $provider): array
    {
        $credentials = $this->probe->configuredKey($provider, 'api_key');

        try {
            return $this->adapters->get(AiModelCatalog::class, $provider)->models($credentials);
        } catch (ProviderCallFailed $failure) {
            throw IntegrationRejected::providerError($failure->kind);
        }
    }
}

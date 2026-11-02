<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Contracts\Integrations\SmsSenderDirectory;
use App\Data\Integrations\SmsSender;
use App\Enums\Integrations\IntegrationProvider;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\ProviderAdapterRegistry;

final readonly class ListTaqnyatSenders
{
    public function __construct(private IntegrationProbe $probe, private ProviderAdapterRegistry $adapters) {}

    /**
     * @return list<SmsSender>
     */
    public function handle(): array
    {
        $credentials = $this->probe->configuredKey(IntegrationProvider::Taqnyat, 'bearer_token');

        try {
            return $this->adapters->get(SmsSenderDirectory::class, IntegrationProvider::Taqnyat)->senders($credentials);
        } catch (ProviderCallFailed $failure) {
            throw IntegrationRejected::providerError($failure->kind);
        }
    }
}

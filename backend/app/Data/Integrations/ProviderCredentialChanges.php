<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;

final readonly class ProviderCredentialChanges
{
    public function __construct(
        public IntegrationProvider $provider,
        public ?IntegrationMode $mode,
        public CredentialChanges $changes,
    ) {}
}

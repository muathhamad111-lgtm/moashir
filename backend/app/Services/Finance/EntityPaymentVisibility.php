<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationCredentialsResolver;

final readonly class EntityPaymentVisibility
{
    public function __construct(private IntegrationCredentialsResolver $credentials) {}

    public function hidesTestPayments(): bool
    {
        $gateway = $this->credentials->resolve(IntegrationKind::Payment);

        return $gateway->isConfigured() && $gateway->mode === IntegrationMode::Live;
    }
}

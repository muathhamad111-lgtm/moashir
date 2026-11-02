<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationProvider;

final readonly class ActivationRequest
{
    public function __construct(public ?IntegrationProvider $provider) {}

    public function deactivates(): bool
    {
        return ! $this->provider instanceof IntegrationProvider;
    }
}

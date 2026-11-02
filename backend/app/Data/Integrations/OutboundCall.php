<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\IntegrationProvider;

final readonly class OutboundCall
{
    public function __construct(
        public IntegrationProvider $provider,
        public string $operation,
        public string $host,
        public int $timeoutSeconds,
    ) {}
}

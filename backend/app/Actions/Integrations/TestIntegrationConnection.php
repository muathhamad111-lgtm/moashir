<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Integrations\ConnectionTestResult;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationProbe;

final readonly class TestIntegrationConnection
{
    public function __construct(private IntegrationProbe $probe) {}

    public function handle(User $actor, IntegrationProvider $provider, ?IntegrationMode $mode = null): ConnectionTestResult
    {
        return $this->probe->test($provider, $mode, $actor);
    }
}

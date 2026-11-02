<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Contracts\Integrations\IntegrationConnectionTester;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Integrations\Ai\AiCatalogCheck;

final readonly class AnthropicConnectionTester implements IntegrationConnectionTester
{
    public function __construct(private AnthropicProvider $catalog) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Anthropic;
    }

    public function test(ResolvedCredentials $credentials): ConnectionTestResult
    {
        return AiCatalogCheck::run($this->catalog, $credentials);
    }
}

<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;

interface IntegrationConnectionTester extends ServesProvider
{
    public function test(ResolvedCredentials $credentials): ConnectionTestResult;
}

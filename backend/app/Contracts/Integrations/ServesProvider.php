<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Enums\Integrations\IntegrationProvider;

interface ServesProvider
{
    public function provider(): IntegrationProvider;
}

<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Integrations\IntegrationMode;
use App\Services\Platform\DemoPosture;

final readonly class BankTransferMode
{
    public function __construct(private DemoPosture $posture) {}

    public function current(): IntegrationMode
    {
        return $this->posture->isRealProduction() ? IntegrationMode::Live : IntegrationMode::Test;
    }
}

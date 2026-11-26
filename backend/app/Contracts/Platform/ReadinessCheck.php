<?php

declare(strict_types=1);

namespace App\Contracts\Platform;

use App\Enums\Platform\ReadinessCheckStatus;

interface ReadinessCheck
{
    public function name(): string;

    public function isCritical(): bool;

    public function status(): ReadinessCheckStatus;
}

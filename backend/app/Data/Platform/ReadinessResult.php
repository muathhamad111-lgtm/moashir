<?php

declare(strict_types=1);

namespace App\Data\Platform;

use App\Enums\Platform\ReadinessCheckStatus;

final readonly class ReadinessResult
{
    public function __construct(public string $name, public bool $isCritical, public ReadinessCheckStatus $status) {}

    public function isBlocking(): bool
    {
        return $this->isCritical && $this->status === ReadinessCheckStatus::Failing;
    }
}

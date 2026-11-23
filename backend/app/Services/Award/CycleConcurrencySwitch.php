<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\AwardCycleConcurrency;

final readonly class CycleConcurrencySwitch
{
    public function __construct(private AwardCycleConcurrencyReader $current) {}

    public function changesTo(?AwardCycleConcurrency $target): bool
    {
        return $target instanceof AwardCycleConcurrency && $target !== $this->current->read();
    }
}

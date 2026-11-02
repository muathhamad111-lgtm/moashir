<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Models\Award\AwardCycle;
use App\Services\Award\CurrentCycleReader;

final readonly class GetPublicCurrentCycle
{
    public function __construct(private CurrentCycleReader $cycles) {}

    public function handle(): ?AwardCycle
    {
        return $this->cycles->running();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CycleConcurrencyState;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;

final readonly class CycleConcurrencyStateReader
{
    public function __construct(private AwardCycleConcurrencyReader $concurrency) {}

    public function read(): CycleConcurrencyState
    {
        return new CycleConcurrencyState(
            $this->concurrency->read(),
            AwardCycle::query()->running()->count(),
            AwardCycle::query()->where('status', AwardCycleStatus::SubmissionOpen)->count(),
        );
    }
}

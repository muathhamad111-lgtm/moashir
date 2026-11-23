<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\AwardCycleConcurrency;

final readonly class CycleConcurrencyState
{
    public function __construct(public AwardCycleConcurrency $mode, public int $runningCount, public int $openForSubmissionCount) {}

    /**
     * @return array{running: int, open_for_submission: int}
     */
    public function runningCyclesToArray(): array
    {
        return ['running' => $this->runningCount, 'open_for_submission' => $this->openForSubmissionCount];
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs\Award;

use App\Actions\Award\IssueCycleCertificates;
use App\Models\Award\AwardCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Every certificate the requests of an announced cycle earned.
 * Idempotent: a retry or a duplicate run issues nothing new.
 */
final class IssueCycleCertificatesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $cycleId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'issue-cycle-certificates:'.$this->cycleId;
    }

    public function handle(IssueCycleCertificates $action): void
    {
        $model = AwardCycle::query()->find($this->cycleId);

        if ($model instanceof AwardCycle) {
            $action->handle($model);
        }
    }
}

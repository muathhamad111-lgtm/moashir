<?php

declare(strict_types=1);

namespace App\Jobs\Award;

use App\Actions\Award\BackfillParticipationCertificates;
use App\Models\Award\AwardCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Participation certificates for the requests already assessed when the setting is turned on.
 * Idempotent: a retry or a duplicate run issues nothing new.
 */
final class BackfillParticipationCertificatesJob implements ShouldBeUnique, ShouldQueue
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
        return 'backfill-participation-certificates:'.$this->cycleId;
    }

    public function handle(BackfillParticipationCertificates $action): void
    {
        $model = AwardCycle::query()->find($this->cycleId);

        if ($model instanceof AwardCycle) {
            $action->handle($model);
        }
    }
}

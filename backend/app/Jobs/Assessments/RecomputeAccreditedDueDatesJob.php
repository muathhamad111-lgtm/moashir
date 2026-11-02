<?php

declare(strict_types=1);

namespace App\Jobs\Assessments;

use App\Actions\Assessments\RecomputeAccreditedDueDates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class RecomputeAccreditedDueDatesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public int $uniqueFor = 600;

    public function handle(RecomputeAccreditedDueDates $recompute): void
    {
        $recompute->handle();
    }
}

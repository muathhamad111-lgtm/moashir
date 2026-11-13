<?php

declare(strict_types=1);

namespace App\Jobs\Platform;

use App\Services\Platform\Readiness\QueueHeartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class RecordQueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 60;

    public function handle(QueueHeartbeat $heartbeat): void
    {
        $heartbeat->beat();
    }
}

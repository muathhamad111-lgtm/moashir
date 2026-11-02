<?php

declare(strict_types=1);

namespace App\Jobs\Finance;

use App\Actions\Finance\ExpireDuePayments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class ExpirePaymentsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function handle(ExpireDuePayments $expire): void
    {
        $expire->handle();
    }
}

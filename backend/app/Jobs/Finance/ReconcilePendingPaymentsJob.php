<?php

declare(strict_types=1);

namespace App\Jobs\Finance;

use App\Actions\Finance\ReconcilePendingPayments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class ReconcilePendingPaymentsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function handle(ReconcilePendingPayments $reconcile): void
    {
        $reconcile->handle();
    }
}

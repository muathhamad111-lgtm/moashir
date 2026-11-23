<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\AwardCycle;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The schedule reached a cycle's submission start but could not open it, because the cycle links no initial assessor.
 * It is dispatched after the refused open has rolled back, outside any transaction, so it goes out at once.
 */
final readonly class CycleOpenBlocked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public AwardCycle $cycle) {}
}

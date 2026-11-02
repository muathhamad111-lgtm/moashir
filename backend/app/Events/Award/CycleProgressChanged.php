<?php

declare(strict_types=1);

namespace App\Events\Award;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CycleProgressChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $cycleId) {}
}

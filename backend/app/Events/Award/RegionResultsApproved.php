<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class RegionResultsApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public AwardCycle $cycle, public Region $region, public int $winnersCount, public int $actorId) {}
}

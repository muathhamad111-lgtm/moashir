<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Data\Award\CommitteeDistribution;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CommitteeRequestsDistributed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CommitteeDistribution $distribution, public ?int $actorId) {}
}

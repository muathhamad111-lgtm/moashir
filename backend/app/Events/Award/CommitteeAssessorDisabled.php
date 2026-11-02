<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\CycleCommitteeAssessor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CommitteeAssessorDisabled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CycleCommitteeAssessor $member, public int $actorId) {}
}

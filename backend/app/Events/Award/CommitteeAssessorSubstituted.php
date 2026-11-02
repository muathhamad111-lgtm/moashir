<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\CycleCommitteeAssessor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CommitteeAssessorSubstituted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CycleCommitteeAssessor $substitute, public CycleCommitteeAssessor $replaced, public int $actorId) {}
}

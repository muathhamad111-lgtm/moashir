<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\AwardCycle;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SubmissionDeadlineApproaching implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $draftIds
     */
    public function __construct(public AwardCycle $cycle, public int $daysBefore, public array $draftIds) {}
}
